<?php

declare(strict_types=1);

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

file_put_contents($dir . '/blocks/upper.json', json_encode([
	'name' => 'upper', 'command' => 'tr',
	'args' => [['a-z', 'A-Z']],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/workflows/loud.json', json_encode([
	'name' => 'loud',
	'description' => 'Amplifies what it gets.',
	'inputs' => ['who' => ['required' => true]],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => 'hello {%who%}'],
			'out' => ['result' => 'greeting'],
		],
		[
			'type' => 'run', 'block' => 'upper',
			'in' => ['stdin' => '{%greeting%}'],
		],
	],
]));

$bin = escapeshellarg(__DIR__ . '/../../bin/donut');

/**
 * Definitions are taken from the profile (DONUT_HOME/DONUT_PROFILE), the
 * working directory stays the fixture — steps run from it and CWD is
 * keyed off it.
 *
 * The environment is passed to the process whole, not appended to the
 * inherited one: PATH has to be there both for `php` and for the steps'
 * commands (echo, tr).
 *
 * @return array{int, string, string}
 */
function donut(string $dir, string $bin, string $args): array
{
	// descriptor 0 is pinned on purpose: donut reads its stdin when it's
	// not a terminal. Without this it would depend on what the process
	// inherited, and could get stuck reading.
	$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$env = [
		'PATH' => (string) getenv('PATH'),
		'DONUT_HOME' => dirname($dir),
		'DONUT_PROFILE' => basename($dir),
	];
	$process = proc_open("php {$bin} {$args}", $descriptors, $pipes, $dir, $env);
	Assert::type('resource', $process);
	fclose($pipes[0]);
	$out = (string) stream_get_contents($pipes[1]);
	$err = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [proc_close($process), $out, $err];
}


// a real process: the run succeeds and the last step prints to stdout
[$code, $out] = donut($dir, $bin, 'loud --who=world');
Assert::same(0, $code);
Assert::contains('HELLO WORLD', $out);

// listing
[$code, $out] = donut($dir, $bin, '--list');
Assert::same(0, $code);
Assert::contains('loud', $out);
Assert::contains('Amplifies what it gets.', $out);

// help
[$code, $out] = donut($dir, $bin, 'loud --help');
Assert::same(0, $code);
Assert::contains('--who=', $out);

// a missing required input: code 2, message on stderr, nothing on stdout
[$code, $out, $err] = donut($dir, $bin, 'loud');
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('who', $err);

// an unknown argument: code 2
[$code, , $err] = donut($dir, $bin, 'loud --who=x --unknown=y');
Assert::same(2, $code);
Assert::contains('unknown', $err);

// The working directory doesn't decide the definitions: a run from /tmp
// finds the workflow the same way, because the profile is in the
// environment.
$elsewhere = TEMP_DIR . '/elsewhere';
FileSystem::createDir($elsewhere);

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = [
	'PATH' => (string) getenv('PATH'),
	'DONUT_HOME' => dirname($dir),
	'DONUT_PROFILE' => basename($dir),
];
$process = proc_open("php {$bin} --list", $descriptors, $pipes, $elsewhere, $env);
Assert::type('resource', $process);
fclose($pipes[0]);
$out = (string) stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);

Assert::same(0, proc_close($process));
Assert::contains('loud', $out);

FileSystem::delete(TEMP_DIR);
