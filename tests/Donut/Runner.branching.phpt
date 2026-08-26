<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Runner\ProcessResult;
use Donut\Runner\ProcessRunner;
use Donut\Runner\Reporter;
use Donut\Runner\Runner;
use Donut\Runner\NullReporter;
use Donut\Runner\RunFailedException;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo', 'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

final class RecordingProcesses implements ProcessRunner
{
	/** @var array<int, list<string>> */
	public array $args = [];

	public function run(string $command, array $args, string $stdin, bool $captureStdout, bool $captureStderr, ?int $timeout): ProcessResult
	{
		$this->args[] = $args;

		return new ProcessResult('', null, 0);
	}
}

final class RecordingReporter implements Reporter
{
	/** @var array<int, array{string, string}> */
	public array $lines = [];

	public function step(string $path, string $label): void
	{
		$this->lines[] = [$path, $label];
	}

	public function warning(string $message): void
	{
	}
}

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;

$run = function (array $data, ProcessRunner $procs, array $initial = []) use ($repo, $parser): array {
	return (new Runner($repo, $procs, new NullReporter))->run($parser->parseArray($data, 'w.json'), $initial);
};

// if: only the branch that matched runs
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['a' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 'yes'],
		'then' => [['type' => 'set', 'key' => 'v', 'value' => 'then']],
		'else' => [['type' => 'set', 'key' => 'v', 'value' => 'else']],
	]],
], $procs, ['a' => 'yes']);
Assert::same('then', $map['v']);

$map = $run([
	'name' => 'w',
	'inputs' => ['a' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 'yes'],
		'then' => [['type' => 'set', 'key' => 'v', 'value' => 'then']],
		'else' => [['type' => 'set', 'key' => 'v', 'value' => 'else']],
	]],
], $procs, ['a' => 'no']);
Assert::same('else', $map['v']);

// a branch has no scope of its own — a write is visible even after the if
$map = $run([
	'name' => 'w',
	'inputs' => ['a' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'v', 'value' => 'inside']],
			'else' => [['type' => 'set', 'key' => 'v', 'value' => 'outside']],
		],
		['type' => 'set', 'key' => 'after', 'value' => 'seen-{%v%}'],
	],
], $procs, ['a' => 'x']);
Assert::same('seen-inside', $map['after']);

// a missing else simply does nothing
$map = $run([
	'name' => 'w',
	'inputs' => ['a' => []],
	'steps' => [
		['type' => 'set', 'key' => 'v', 'value' => 'original'],
		[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 'no'],
			'then' => [['type' => 'set', 'key' => 'v', 'value' => 'changed']],
		],
	],
], $procs, ['a' => 'yes']);
Assert::same('original', $map['v']);

// all four if cases above use only set — no process ever started
Assert::same([], $procs->args);

// a key written only in then is just a warning at validation time ("may or
// may not exist"); when else is then taken and the key is read, the run
// fails as a MissingKeyException — but with the step's path, not just the key name
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['a' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 'yes'],
				'then' => [['type' => 'set', 'key' => 'x', 'value' => 'only-then']],
			],
			['type' => 'set', 'key' => 'after', 'value' => '{%x%}'],
		],
	], $procs, ['a' => 'no']),
	RunFailedException::class,
	"w.json:steps[1]: Klíč 'x' v mapě neexistuje."
);

// foreach: iterates over lines, empty ones are skipped, \r is stripped
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['list' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%list%}', 'as' => 'line',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%line%}']]],
	]],
], $procs, ['list' => "a\r\n\nb\nc"]);
Assert::same([['a'], ['b'], ['c']], $procs->args);

// after the loop, the key keeps the last value
Assert::same('c', $map['line']);

// an empty input means zero iterations
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['list' => ['required' => false]],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%list%}', 'as' => 'line',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%line%}']]],
	]],
], $procs, ['list' => '']);
Assert::same([], $procs->args);
Assert::false(isset($map['line']));

// a foreach nested inside a foreach
$procs = new RecordingProcesses;
$run([
	'name' => 'w',
	'inputs' => ['outer' => [], 'inner' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%outer%}', 'as' => 'x',
		'steps' => [[
			'type' => 'foreach', 'over' => '{%inner%}', 'as' => 'y',
			'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%x%}{%y%}']]],
		]],
	]],
], $procs, ['outer' => "1\n2", 'inner' => "a\nb"]);
Assert::same([['1a'], ['1b'], ['2a'], ['2b']], $procs->args);

// reporting: the value belongs to the foreach's own path, the body reports its
// own paths, both repeat under each iteration — see the Progress reporting
// section in the design
$procs = new RecordingProcesses;
$reporter = new RecordingReporter;
(new Runner($repo, $procs, $reporter))->run($parser->parseArray([
	'name' => 'w',
	'inputs' => ['outer' => [], 'inner' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%outer%}', 'as' => 'x',
		'steps' => [[
			'type' => 'foreach', 'over' => '{%inner%}', 'as' => 'y',
			'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%x%}{%y%}']]],
		]],
	]],
], 'w.json'), ['outer' => "1\n2", 'inner' => "a\nb"]);

Assert::same([
	['w.json:steps[0]', 'foreach'],
	['w.json:steps[0]', 'x=1'],
	['w.json:steps[0].steps[0]', 'foreach'],
	['w.json:steps[0].steps[0]', 'y=a'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0].steps[0]', 'y=b'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0]', 'x=2'],
	['w.json:steps[0].steps[0]', 'foreach'],
	['w.json:steps[0].steps[0]', 'y=a'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0].steps[0]', 'y=b'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
], $reporter->lines);

FileSystem::delete(TEMP_DIR);
