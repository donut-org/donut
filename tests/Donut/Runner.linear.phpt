<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Runner\ProcessResult;
use Donut\Runner\ProcessRunner;
use Donut\Runner\Runner;
use Donut\Runner\NullReporter;
use Donut\Runner\RunFailedException;
use Nette\Utils\FileSystem;
use Nette\Utils\ProcessTimeoutException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo', 'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

file_put_contents($dir . '/cat.json', json_encode([
	'name' => 'cat', 'command' => 'cat',
	'args' => [],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/maybe.json', json_encode([
	'name' => 'maybe', 'command' => 'maybe',
	'args' => [],
	'allow_failure' => [0, 1],
]));

/**
 * A fake ProcessRunner: runs nothing, just records what it would have run,
 * and returns pre-supplied results.
 */
final class FakeProcesses implements ProcessRunner
{
	/** @var array<int, array{string, list<string>, string, bool, bool, ?int}> */
	public array $calls = [];

	/** @param array<int, ProcessResult|\Throwable> $results */
	public function __construct(private array $results = [])
	{
	}

	public function run(string $command, array $args, string $stdin, bool $captureStdout, bool $captureStderr, ?int $timeout): ProcessResult
	{
		$this->calls[] = [$command, $args, $stdin, $captureStdout, $captureStderr, $timeout];

		$result = \array_shift($this->results) ?? new ProcessResult('', null, 0);

		if ($result instanceof \Throwable) {
			throw $result;
		}

		return $result;
	}
}

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;

$run = function (array $data, FakeProcesses $procs, array $initial = []) use ($repo, $parser): array {
	$runner = new Runner($repo, $procs, new NullReporter);
	return $runner->run($parser->parseArray($data, 'w.json'), $initial);
};

// set writes into the map and can read what's already in it
$procs = new FakeProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['a' => []],
	'steps' => [
		['type' => 'set', 'key' => 'b', 'value' => '{%a%}-x'],
		['type' => 'set', 'key' => 'b', 'value' => '{%b%}-y'],
	],
], $procs, ['a' => 'v']);
Assert::same('v-x-y', $map['b']);
Assert::same([], $procs->calls);

// run: assembled command line and channel writes
$procs = new FakeProcesses([new ProcessResult('vysledek', null, 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['text' => '{%t%}'],
		'out' => ['result' => 'r', 'exit_code' => 'rc'],
	]],
], $procs, ['t' => 'ahoj']);

Assert::same('vysledek', $map['r']);
Assert::same('0', $map['rc']);
Assert::count(1, $procs->calls);
Assert::same(['echo', ['ahoj'], '', true, false, 60], $procs->calls[0]);

// stdin is filled from in, and stderr is captured only when the step maps it
$procs = new FakeProcesses([new ProcessResult('', 'chyba', 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['in' => []],
	'steps' => [[
		'type' => 'run', 'block' => 'cat',
		'in' => ['stdin' => '{%in%}'],
		'out' => ['stderr' => 'e'],
	]],
], $procs, ['in' => 'text']);

Assert::same('chyba', $map['e']);
Assert::same(['cat', [], 'text', false, true, 60], $procs->calls[0]);

// a step without out doesn't change the map — except STDIN and CWD, which run() fills in itself
$procs = new FakeProcesses([new ProcessResult('nic', null, 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%t%}']]],
], $procs, ['t' => 'x']);
Assert::same(['t' => 'x', 'STDIN' => '', 'CWD' => getcwd()], $map);

// a step without result in out doesn't capture stdout
$procs = new FakeProcesses([new ProcessResult(null, null, 0)]);
$run([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%t%}']]],
], $procs, ['t' => 'x']);
Assert::false($procs->calls[0][3]);

// a step with result in out captures it
$procs = new FakeProcesses([new ProcessResult('v', null, 0)]);
$run([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['text' => '{%t%}'],
		'out' => ['result' => 'r'],
	]],
], $procs, ['t' => 'x']);
Assert::true($procs->calls[0][3]);

// a non-zero exit code stops the run
$procs = new FakeProcesses([new ProcessResult('', null, 3)]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%t%}']]],
	], $procs, ['t' => 'x']),
	RunFailedException::class,
	'w.json:steps[0]: block "echo" finished with exit code 3.'
);

// a block's allow_failure lets an allowed code through and channels get written
$procs = new FakeProcesses([new ProcessResult('', null, 1), new ProcessResult('po', null, 0)]);
$map = $run([
	'name' => 'w',
	'steps' => [
		['type' => 'run', 'block' => 'maybe', 'out' => ['exit_code' => 'rc']],
		['type' => 'set', 'key' => 'next', 'value' => 'ran-{%rc%}'],
	],
], $procs);
Assert::same('1', $map['rc']);
Assert::same('ran-1', $map['next']);

// a code outside the list stops the run even with allow_failure
$procs = new FakeProcesses([new ProcessResult('', null, 5)]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'maybe']],
	], $procs),
	RunFailedException::class,
	'w.json:steps[0]: block "maybe" finished with exit code 5.'
);

// a step overrides both the block's allow_failure and its timeout
$procs = new FakeProcesses([new ProcessResult('', null, 9)]);
$map = $run([
	'name' => 'w',
	'steps' => [[
		'type' => 'run', 'block' => 'maybe',
		'allow_failure' => true, 'timeout' => 5,
		'out' => ['exit_code' => 'rc'],
	]],
], $procs);
Assert::same('9', $map['rc']);
Assert::same(5, $procs->calls[0][5]);

// a timeout is not an exit code, allow_failure doesn't absorb it
$procs = new FakeProcesses([new ProcessTimeoutException('timed out')]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'maybe', 'allow_failure' => true]],
	], $procs),
	RunFailedException::class,
	'w.json:steps[0]: block "maybe" exceeded the 60 s limit.'
);

// a workflow with a validation error doesn't run at all
$procs = new FakeProcesses;
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'missing']],
	], $procs),
	RunFailedException::class,
	'%A%validation failed%A%'
);
Assert::same([], $procs->calls);

// several validation findings appear in the message one per line
$procs = new FakeProcesses;
$e = Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'missing1'],
			['type' => 'run', 'block' => 'missing2'],
		],
	], $procs),
	RunFailedException::class,
	'%A%validation failed%A%'
);
$lines = explode("\n", $e->getMessage());
Assert::count(3, $lines);
Assert::same('w.json:steps[0]: block "missing1" does not exist', $lines[1]);
Assert::same('w.json:steps[1]: block "missing2" does not exist', $lines[2]);

// run() fills in on its own the initial map the validator assumes: an
// input's default, when the caller doesn't supply it
$procs = new FakeProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['tag' => ['required' => false, 'default' => 'latest']],
	'steps' => [['type' => 'set', 'key' => 'out', 'value' => '{%tag%}']],
], $procs);
Assert::same('latest', $map['out']);

// a value from the caller overrides the default
$map = $run([
	'name' => 'w',
	'inputs' => ['tag' => ['required' => false, 'default' => 'latest']],
	'steps' => [['type' => 'set', 'key' => 'out', 'value' => '{%tag%}']],
], $procs, ['tag' => 'v2']);
Assert::same('v2', $map['out']);

// an empty string from the caller is the same as not supplying one — the default applies
$map = $run([
	'name' => 'w',
	'inputs' => ['tag' => ['required' => false, 'default' => 'latest']],
	'steps' => [['type' => 'set', 'key' => 'out', 'value' => '{%tag%}']],
], $procs, ['tag' => '']);
Assert::same('latest', $map['out']);

// an optional input without a default, which the caller doesn't supply, is
// in the map as an empty string — not a missing key (spec sections 1 and 4:
// "unfilled" and '' are one and the same thing)
$map = $run([
	'name' => 'w',
	'inputs' => ['tag' => ['required' => false]],
	'steps' => [['type' => 'set', 'key' => 'out', 'value' => '{%tag%}']],
], $procs);
Assert::same('', $map['out']);

// a missing required input with no value is an error before the first step runs
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['tag' => ['required' => true]],
		'steps' => [],
	], $procs),
	RunFailedException::class,
	'w.json: required input "tag" has no value.'
);
Assert::same([], $procs->calls);

// CWD is in the map and isn't empty, STDIN is in the map empty when nothing arrived
$map = $run(['name' => 'w', 'steps' => []], $procs);
Assert::same(getcwd(), $map['CWD']);
Assert::same('', $map['STDIN']);

// run() doesn't overwrite a CWD supplied by the caller
$map = $run(['name' => 'w', 'steps' => []], $procs, ['CWD' => '/from/caller']);
Assert::same('/from/caller', $map['CWD']);

// failed validation: the run never started at all
$procs = new FakeProcesses;
Assert::exception(
	fn() => $run(['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'missing']]], $procs),
	Donut\Runner\CannotStartException::class
);
Assert::same([], $procs->calls);

// a missing required workflow input: it also didn't start
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['required' => ['required' => true]],
		'steps' => [['type' => 'set', 'key' => 'v', 'value' => '{%required%}']],
	], new FakeProcesses),
	Donut\Runner\CannotStartException::class,
	'w.json: required input "required" has no value.'
);

// a step failure stays RunFailedException, not CannotStartException
$procs = new FakeProcesses([new ProcessResult('', null, 3)]);
$e = Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%t%}']]],
	], $procs, ['t' => 'x']),
	Donut\Runner\RunFailedException::class
);
Assert::false($e instanceof Donut\Runner\CannotStartException);

FileSystem::delete(TEMP_DIR);
