<?php

declare(strict_types=1);

use Donut\Cli\Application;
use Donut\Profile;
use Donut\Runner\ProcessResult;
use Donut\Runner\ProcessRunner;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/home';
FileSystem::createDir($dir . '/blocks');
FileSystem::createDir($dir . '/workflows');

file_put_contents($dir . '/blocks/echo.json', json_encode([
	'name' => 'echo', 'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

file_put_contents($dir . '/blocks/fail.json', json_encode([
	'name' => 'fail', 'command' => '/usr/bin/false', 'args' => [],
]));

file_put_contents($dir . '/workflows/greet.json', json_encode([
	'name' => 'greet',
	'description' => 'Greets.',
	'inputs' => [
		'who' => ['required' => true, 'description' => 'Whom to greet'],
		'tag' => ['required' => false, 'default' => 'hi', 'description' => 'Greeting'],
	],
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['text' => '{%tag%} {%who%}'],
	]],
]));

file_put_contents($dir . '/workflows/stumbles.json', json_encode([
	'name' => 'stumbles',
	'description' => 'Always fails.',
	'steps' => [['type' => 'run', 'block' => 'fail']],
]));

file_put_contents($dir . '/workflows/no-default.json', json_encode([
	'name' => 'no-default',
	'description' => 'Optional input without a default.',
	'inputs' => [
		'tag' => ['required' => false],
	],
	'steps' => [
		['type' => 'set', 'key' => 'read', 'value' => '{%tag%}'],
	],
]));

/**
 * Note: only what Application writes is captured — that is --list, --help
 * and error messages. The standard output of executed steps goes to the
 * real process STDOUT, not to the substituted stream, so it can't be
 * verified here. That's what the acceptance test in Cli.acceptance.phpt is
 * for — it runs donut as a separate process via proc_open.
 *
 * @param  array<int, string> $argv
 * @return array{int, string, string} code, stdout, stderr
 */
function run(string $dir, array $argv, ?ProcessRunner $processes = null): array
{
	$out = fopen('php://memory', 'r+');
	$err = fopen('php://memory', 'r+');
	$code = (new Application(new Profile('testprofile', $dir), $out, $err, '', $processes))->run($argv);
	rewind($out);
	rewind($err);
	$result = [$code, stream_get_contents($out), stream_get_contents($err)];
	fclose($out);
	fclose($err);

	return $result;
}


// --list prints workflows with description, alphabetically
[$code, $out] = run($dir, ['donut', '--list']);
Assert::same(0, $code);
Assert::contains('greet', $out);
Assert::contains('Greets.', $out);
Assert::contains('stumbles', $out);
Assert::true(strpos($out, 'greet') < strpos($out, 'stumbles'));

// help for a workflow prints inputs, required-ness and description — for
// the one they belong to, not just somewhere in the output; otherwise a
// swapped ternary in Application (required <-> optional) wouldn't fail
// the test
[$code, $out] = run($dir, ['donut', 'greet', '--help']);
Assert::same(0, $code);

$lines = \explode("\n", $out);
$who = null;
$tag = null;

foreach ($lines as $line) {
	if (\str_contains($line, '--who=')) {
		$who = $line;
	} elseif (\str_contains($line, '--tag=')) {
		$tag = $line;
	}
}

Assert::notNull($who, 'line with --who= exists');
Assert::contains('required', $who);
Assert::notContains('optional', $who);
Assert::contains('Whom to greet', $who);

Assert::notNull($tag, 'line with --tag= exists');
Assert::contains('optional', $tag);
Assert::notContains('required', $tag);

// the run completes, code 0
[$code] = run($dir, ['donut', 'greet', '--who=world']);
Assert::same(0, $code);

// an unpassed optional input without a default reads as '' — not code 1
[$code] = run($dir, ['donut', 'no-default']);
Assert::same(0, $code);

// a step failure is code 1
[$code, , $err] = run($dir, ['donut', 'stumbles']);
Assert::same(1, $code);
Assert::contains('exit code 1', $err);

// a missing required input is code 2
[$code, , $err] = run($dir, ['donut', 'greet']);
Assert::same(2, $code);
Assert::contains('required input "who" has no value', $err);

// an empty required input is the same as unfilled — code 2, not a started run
[$code, , $err] = run($dir, ['donut', 'greet', '--who=']);
Assert::same(2, $code);
Assert::contains('required input "who" has no value', $err);

// a nonexistent workflow is code 2 and the message says where it looked —
// the profile is the sharpest edge of the tool and the most common cause
// of this error
[$code, , $err] = run($dir, ['donut', 'missing']);
Assert::same(2, $code);
Assert::contains('Workflow "missing" does not exist.', $err);
Assert::contains($dir . '/workflows/', $err);
// the workflows/ directory exists — only the file in it is missing, so the
// `mkdir -p` advice would be misleading here (see $empty below, where it
// applies)
Assert::notContains('mkdir', $err);

// an unknown argument is code 2
[$code, , $err] = run($dir, ['donut', 'greet', '--who=x', '--unknown=y']);
Assert::same(2, $code);
Assert::same("Error: Workflow \"greet\" has no input \"unknown\".\n", $err);

// a bare call is an error — usage goes to stderr and stdout stays empty
[$code, $out, $err] = run($dir, ['donut']);
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('donut --list', $err);

// --help without a workflow prints usage and exits with zero — to stdout, it's not an error
[$code, $out, $err] = run($dir, ['donut', '--help']);
Assert::same(0, $code);
Assert::contains('--list', $out);
Assert::same('', $err);

// --list survives a bad file: good workflows go to stdout, bad ones are
// reported to stderr and the code is 2. One broken file must not hide the
// rest — especially not while the directory is a work in progress and
// someone needs to see what they have.
file_put_contents($dir . '/workflows/broken.json', '{ this is not JSON');

[$code, $out, $err] = run($dir, ['donut', '--list']);
Assert::same(2, $code);
Assert::contains('greet', $out);
Assert::contains('stumbles', $out);
Assert::notContains('broken', $out);
Assert::contains('broken.json', $err);

unlink($dir . '/workflows/broken.json');

// an unexpected Donut\Exception is caught and ends with a two. Nothing
// throws it today, so it has to be substituted in — otherwise that branch
// couldn't be run at all.
$explodes = new class implements ProcessRunner {
	/**
	 * @param list<string> $args
	 */
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStdout,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult
	{
		throw new Donut\Exception('broken internals');
	}
};

[$code, , $err] = run($dir, ['donut', 'greet', '--who=world'], $explodes);
Assert::same(2, $code);
Assert::contains('Internal tool error: broken internals', $err);

// --- the usage says which profile is being read from ---
// Without this, "donut --list prints nothing" can't be debugged: the user
// doesn't see where the tool looked, and the working directory no longer
// tells them.
[$code, $out] = run($dir, ['donut', '--help']);
Assert::same(0, $code);
Assert::contains('Profile: testprofile', $out);
Assert::contains($dir, $out);
Assert::contains('DONUT_PROFILE=', $out);
Assert::contains('DONUT_HOME=', $out);

// --- missing workflows directory: --list isn't silence, it's a hint ---
// An empty listing and a missing profile look the same on the terminal. A
// fresh installation is exactly the case where you need to see the
// difference.
$empty = TEMP_DIR . '/empty-profile';
FileSystem::createDir($empty);

[$code, $out, $err] = run($empty, ['donut', '--list']);
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('does not exist', $err);
Assert::contains('mkdir -p ' . $empty . '/workflows', $err);

// --- and the same when trying to run a workflow ---
[$code, , $err] = run($empty, ['donut', 'anything']);
Assert::same(2, $code);
Assert::contains('mkdir -p ' . $empty . '/workflows', $err);

// --- only blocks/ is missing: the workflow exists, the run only hits it
// later ---
// workflows/ is fine, so loadWorkflow() won't give advice — runWorkflow()
// must have its own guard, otherwise the user only gets "directory does
// not exist" with no advice what to do about it.
$workflowsOnly = TEMP_DIR . '/workflows-only';
FileSystem::createDir($workflowsOnly . '/workflows');
file_put_contents($workflowsOnly . '/workflows/empty.json', json_encode([
	'name' => 'empty',
	'steps' => [],
]));

[$code, , $err] = run($workflowsOnly, ['donut', 'empty']);
Assert::same(2, $code);
Assert::contains('mkdir -p ' . $workflowsOnly . '/blocks', $err);

// --- main() translates an impossible environment into code 2, not a fatal
// error ---
// The only reason main() exists: bin/donut must not contain a branch that
// can't be tested.
$out = fopen('php://memory', 'r+');
$err = fopen('php://memory', 'r+');
$code = Application::main(['donut', '--list'], [], $out, $err);
rewind($err);
$message = stream_get_contents($err);
fclose($out);
fclose($err);

Assert::same(2, $code);
Assert::contains('DONUT_HOME', $message);

// --- main() with a usable environment reaches Application ---
$out = fopen('php://memory', 'r+');
$err = fopen('php://memory', 'r+');
$code = Application::main(
	['donut', '--list'],
	['DONUT_HOME' => dirname($dir), 'DONUT_PROFILE' => basename($dir)],
	$out,
	$err,
);
rewind($out);
$listing = stream_get_contents($out);
fclose($out);
fclose($err);

Assert::same(0, $code);
Assert::contains('greet', $listing);

FileSystem::delete(TEMP_DIR);
