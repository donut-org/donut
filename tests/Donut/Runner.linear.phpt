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
	'args' => [['{%TEXT%}']],
	'inputs' => ['TEXT' => ['required' => true]],
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
 * Falešný ProcessRunner: nic nespouští, jen zaznamenává, co by spustil,
 * a vrací předem připravené výsledky.
 */
final class FakeProcesses implements ProcessRunner
{
	/** @var array<int, array{string, list<string>, string, bool, ?int}> */
	public array $calls = [];

	/** @param array<int, ProcessResult|\Throwable> $results */
	public function __construct(private array $results = [])
	{
	}

	public function run(string $command, array $args, string $stdin, bool $captureStderr, ?int $timeout): ProcessResult
	{
		$this->calls[] = [$command, $args, $stdin, $captureStderr, $timeout];

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

// set zapisuje do mapy a umí číst, co už v ní je
$procs = new FakeProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['A' => []],
	'steps' => [
		['type' => 'set', 'key' => 'B', 'value' => '{%A%}-x'],
		['type' => 'set', 'key' => 'B', 'value' => '{%B%}-y'],
	],
], $procs, ['A' => 'v']);
Assert::same('v-x-y', $map['B']);
Assert::same([], $procs->calls);

// run: poskládaná příkazová řádka a zápis kanálů
$procs = new FakeProcesses([new ProcessResult('vysledek', null, 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['TEXT' => '{%T%}'],
		'out' => ['result' => 'R', 'exit_code' => 'RC'],
	]],
], $procs, ['T' => 'ahoj']);

Assert::same('vysledek', $map['R']);
Assert::same('0', $map['RC']);
Assert::count(1, $procs->calls);
Assert::same(['echo', ['ahoj'], '', false, 60], $procs->calls[0]);

// stdin se plní z in a stderr se zachytává, jen když ho krok mapuje
$procs = new FakeProcesses([new ProcessResult('', 'chyba', 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['IN' => []],
	'steps' => [[
		'type' => 'run', 'block' => 'cat',
		'in' => ['STDIN' => '{%IN%}'],
		'out' => ['stderr' => 'E'],
	]],
], $procs, ['IN' => 'text']);

Assert::same('chyba', $map['E']);
Assert::same(['cat', [], 'text', true, 60], $procs->calls[0]);

// krok bez out mapu nemění
$procs = new FakeProcesses([new ProcessResult('nic', null, 0)]);
$map = $run([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%T%}']]],
], $procs, ['T' => 'x']);
Assert::same(['T' => 'x'], $map);

// nenulový exit code zastaví běh
$procs = new FakeProcesses([new ProcessResult('', null, 3)]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%T%}']]],
	], $procs, ['T' => 'x']),
	RunFailedException::class,
	'w.json:steps[0]: kámen "echo" skončil s exit code 3.'
);

// allow_failure kamene povolený kód pustí dál a kanály se zapíšou
$procs = new FakeProcesses([new ProcessResult('', null, 1), new ProcessResult('po', null, 0)]);
$map = $run([
	'name' => 'w',
	'steps' => [
		['type' => 'run', 'block' => 'maybe', 'out' => ['exit_code' => 'RC']],
		['type' => 'set', 'key' => 'DALSI', 'value' => 'probehlo-{%RC%}'],
	],
], $procs);
Assert::same('1', $map['RC']);
Assert::same('probehlo-1', $map['DALSI']);

// kód mimo seznam zastaví i u allow_failure
$procs = new FakeProcesses([new ProcessResult('', null, 5)]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'maybe']],
	], $procs),
	RunFailedException::class,
	'w.json:steps[0]: kámen "maybe" skončil s exit code 5.'
);

// krok si přepíše allow_failure i timeout kamene
$procs = new FakeProcesses([new ProcessResult('', null, 9)]);
$map = $run([
	'name' => 'w',
	'steps' => [[
		'type' => 'run', 'block' => 'maybe',
		'allow_failure' => true, 'timeout' => 5,
		'out' => ['exit_code' => 'RC'],
	]],
], $procs);
Assert::same('9', $map['RC']);
Assert::same(5, $procs->calls[0][4]);

// vypršení limitu není exit code, allow_failure ho nepohltí
$procs = new FakeProcesses([new ProcessTimeoutException('vypršel čas')]);
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'maybe', 'allow_failure' => true]],
	], $procs),
	RunFailedException::class,
	'w.json:steps[0]: kámen "maybe" překročil limit 60 s.'
);

// workflow s chybou validace se nespustí vůbec
$procs = new FakeProcesses;
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'neexistuje']],
	], $procs),
	RunFailedException::class,
	'%A%validace neprošla%A%'
);
Assert::same([], $procs->calls);

// víc nálezů validace je v hlášce každý na svém řádku
$procs = new FakeProcesses;
$e = Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'neexistuje1'],
			['type' => 'run', 'block' => 'neexistuje2'],
		],
	], $procs),
	RunFailedException::class,
	'%A%validace neprošla%A%'
);
$lines = explode("\n", $e->getMessage());
Assert::count(3, $lines);
Assert::same('w.json:steps[0]: kámen "neexistuje1" neexistuje', $lines[1]);
Assert::same('w.json:steps[1]: kámen "neexistuje2" neexistuje', $lines[2]);

FileSystem::delete(TEMP_DIR);
