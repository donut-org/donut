<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Runner\NetteProcessRunner;
use Donut\Runner\NullReporter;
use Donut\Runner\RunFailedException;
use Donut\Runner\Runner;
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

file_put_contents($dir . '/upper.json', json_encode([
	'name' => 'upper', 'command' => 'tr',
	'args' => [['a-z', 'A-Z']],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/fail.json', json_encode([
	'name' => 'fail', 'command' => '/usr/bin/false',
	'args' => [],
]));

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$runner = new Runner($repo, new NetteProcessRunner, new NullReporter);

$run = fn(array $data, array $initial = []) => $runner->run($parser->parseArray($data, 'w.json'), $initial);

// celé workflow: proces -> mapa -> stdin dalšího procesu -> if -> foreach
$map = $run([
	'name' => 'w',
	'inputs' => ['JMENO' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['TEXT' => 'ahoj {%JMENO%}'],
			'out' => ['result' => 'POZDRAV'],
		],
		[
			'type' => 'run', 'block' => 'upper',
			'in' => ['STDIN' => '{%POZDRAV%}'],
			'out' => ['result' => 'HLASITE'],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%HLASITE%}', 'op' => 'contains', 'right' => 'SVETE'],
			'then' => [['type' => 'set', 'key' => 'KDO', 'value' => 'svet']],
			'else' => [['type' => 'set', 'key' => 'KDO', 'value' => 'nekdo jiny']],
		],
	],
], ['JMENO' => 'svete']);

Assert::same('ahoj svete', $map['POZDRAV']);
Assert::same('AHOJ SVETE', $map['HLASITE']);
Assert::same('svet', $map['KDO']);

// foreach nad skutečným víceřádkovým výstupem procesu
$map = $run([
	'name' => 'w',
	'inputs' => ['RADKY' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['TEXT' => '{%RADKY%}'],
			'out' => ['result' => 'SEZNAM'],
		],
		[
			'type' => 'foreach', 'over' => '{%SEZNAM%}', 'as' => 'R',
			'steps' => [['type' => 'set', 'key' => 'POSLEDNI', 'value' => 'radek-{%R%}']],
		],
	],
], ['RADKY' => "prvni\ndruhy\ntreti"]);

Assert::same('radek-treti', $map['POSLEDNI']);

// koncové odřádkování se odřezává, takže hodnota jde rovnou do argumentu
$map = $run([
	'name' => 'w',
	'steps' => [
		['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => 'x'], 'out' => ['result' => 'V']],
		['type' => 'set', 'key' => 'URL', 'value' => 'https://api/{%V%}/end'],
	],
]);
Assert::same('https://api/x/end', $map['URL']);

// selhání skutečného procesu zastaví běh
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'fail'],
			['type' => 'set', 'key' => 'NEMELO_BY', 'value' => 'x'],
		],
	]),
	RunFailedException::class,
	'w.json:steps[0]: kámen "fail" skončil s exit code 1.'
);

// argumenty neprochází shellem
$map = $run([
	'name' => 'w',
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['TEXT' => 'a; rm -rf /tmp/neexistuje'],
		'out' => ['result' => 'V'],
	]],
]);
Assert::same('a; rm -rf /tmp/neexistuje', $map['V']);

FileSystem::delete(TEMP_DIR);
