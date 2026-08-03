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
	'args' => [['{%TEXT%}']],
	'inputs' => ['TEXT' => ['required' => true]],
]));

final class RecordingProcesses implements ProcessRunner
{
	/** @var array<int, list<string>> */
	public array $args = [];

	public function run(string $command, array $args, string $stdin, bool $captureStderr, ?int $timeout): ProcessResult
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

// if: projde se jen splněná větev
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['A' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'ano'],
		'then' => [['type' => 'set', 'key' => 'V', 'value' => 'then']],
		'else' => [['type' => 'set', 'key' => 'V', 'value' => 'else']],
	]],
], $procs, ['A' => 'ano']);
Assert::same('then', $map['V']);

$map = $run([
	'name' => 'w',
	'inputs' => ['A' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'ano'],
		'then' => [['type' => 'set', 'key' => 'V', 'value' => 'then']],
		'else' => [['type' => 'set', 'key' => 'V', 'value' => 'else']],
	]],
], $procs, ['A' => 'ne']);
Assert::same('else', $map['V']);

// větev nemá vlastní scope — zápis je vidět i za ifem
$map = $run([
	'name' => 'w',
	'inputs' => ['A' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'V', 'value' => 'uvnitr']],
			'else' => [['type' => 'set', 'key' => 'V', 'value' => 'jinak']],
		],
		['type' => 'set', 'key' => 'PO', 'value' => 'videl-{%V%}'],
	],
], $procs, ['A' => 'x']);
Assert::same('videl-uvnitr', $map['PO']);

// chybějící else prostě neudělá nic
$map = $run([
	'name' => 'w',
	'inputs' => ['A' => []],
	'steps' => [
		['type' => 'set', 'key' => 'V', 'value' => 'puvodni'],
		[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'ne'],
			'then' => [['type' => 'set', 'key' => 'V', 'value' => 'zmeneno']],
		],
	],
], $procs, ['A' => 'ano']);
Assert::same('puvodni', $map['V']);

// všechny čtyři if-případy výše používají jen set — žádný proces neměl start
Assert::same([], $procs->args);

// klíč zapsaný jen v then je za validace jen varování ("může, ale nemusí
// existovat"); když se pak vezme else a klíč se čte, běh spadne jako
// MissingKeyException — ale s cestou ke kroku, ne jen se jménem klíče
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'inputs' => ['A' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'ano'],
				'then' => [['type' => 'set', 'key' => 'X', 'value' => 'jen-then']],
			],
			['type' => 'set', 'key' => 'PO', 'value' => '{%X%}'],
		],
	], $procs, ['A' => 'ne']),
	RunFailedException::class,
	"w.json:steps[1]: Klíč 'X' v mapě neexistuje."
);

// foreach: iterace přes řádky, prázdné se přeskočí, \r se odřízne
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['SEZNAM' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%SEZNAM%}', 'as' => 'RADEK',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%RADEK%}']]],
	]],
], $procs, ['SEZNAM' => "a\r\n\nb\nc"]);
Assert::same([['a'], ['b'], ['c']], $procs->args);

// po cyklu zůstává v klíči poslední hodnota
Assert::same('c', $map['RADEK']);

// prázdný vstup znamená nula iterací
$procs = new RecordingProcesses;
$map = $run([
	'name' => 'w',
	'inputs' => ['SEZNAM' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%SEZNAM%}', 'as' => 'RADEK',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%RADEK%}']]],
	]],
], $procs, ['SEZNAM' => '']);
Assert::same([], $procs->args);
Assert::false(isset($map['RADEK']));

// vnořený foreach uvnitř foreach
$procs = new RecordingProcesses;
$run([
	'name' => 'w',
	'inputs' => ['VNEJSI' => [], 'VNITRNI' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%VNEJSI%}', 'as' => 'X',
		'steps' => [[
			'type' => 'foreach', 'over' => '{%VNITRNI%}', 'as' => 'Y',
			'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%X%}{%Y%}']]],
		]],
	]],
], $procs, ['VNEJSI' => "1\n2", 'VNITRNI' => "a\nb"]);
Assert::same([['1a'], ['1b'], ['2a'], ['2b']], $procs->args);

// hlášení: hodnota patří cestě foreache, tělo hlásí svoje vlastní cesty,
// obojí se opakuje pod každou iterací — viz sekce Hlášení průběhu v návrhu
$procs = new RecordingProcesses;
$reporter = new RecordingReporter;
(new Runner($repo, $procs, $reporter))->run($parser->parseArray([
	'name' => 'w',
	'inputs' => ['VNEJSI' => [], 'VNITRNI' => []],
	'steps' => [[
		'type' => 'foreach', 'over' => '{%VNEJSI%}', 'as' => 'X',
		'steps' => [[
			'type' => 'foreach', 'over' => '{%VNITRNI%}', 'as' => 'Y',
			'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%X%}{%Y%}']]],
		]],
	]],
], 'w.json'), ['VNEJSI' => "1\n2", 'VNITRNI' => "a\nb"]);

Assert::same([
	['w.json:steps[0]', 'foreach'],
	['w.json:steps[0]', 'X=1'],
	['w.json:steps[0].steps[0]', 'foreach'],
	['w.json:steps[0].steps[0]', 'Y=a'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0].steps[0]', 'Y=b'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0]', 'X=2'],
	['w.json:steps[0].steps[0]', 'foreach'],
	['w.json:steps[0].steps[0]', 'Y=a'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
	['w.json:steps[0].steps[0]', 'Y=b'],
	['w.json:steps[0].steps[0].steps[0]', 'echo'],
], $reporter->lines);

FileSystem::delete(TEMP_DIR);
