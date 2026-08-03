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
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
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
	'inputs' => ['jmeno' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => 'ahoj {%jmeno%}'],
			'out' => ['result' => 'pozdrav'],
		],
		[
			'type' => 'run', 'block' => 'upper',
			'in' => ['stdin' => '{%pozdrav%}'],
			'out' => ['result' => 'hlasite'],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%hlasite%}', 'op' => 'contains', 'right' => 'SVETE'],
			'then' => [['type' => 'set', 'key' => 'kdo', 'value' => 'svet']],
			'else' => [['type' => 'set', 'key' => 'kdo', 'value' => 'nekdo jiny']],
		],
	],
], ['jmeno' => 'svete']);

Assert::same('ahoj svete', $map['pozdrav']);
Assert::same('AHOJ SVETE', $map['hlasite']);
Assert::same('svet', $map['kdo']);

// foreach nad skutečným víceřádkovým výstupem procesu
$map = $run([
	'name' => 'w',
	'inputs' => ['radky' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => '{%radky%}'],
			'out' => ['result' => 'seznam'],
		],
		[
			'type' => 'foreach', 'over' => '{%seznam%}', 'as' => 'r',
			'steps' => [['type' => 'set', 'key' => 'posledni', 'value' => 'radek-{%r%}']],
		],
	],
], ['radky' => "prvni\ndruhy\ntreti"]);

Assert::same('radek-treti', $map['posledni']);

// koncové odřádkování se odřezává, takže hodnota jde rovnou do argumentu
$map = $run([
	'name' => 'w',
	'steps' => [
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => 'x'], 'out' => ['result' => 'v']],
		['type' => 'set', 'key' => 'url', 'value' => 'https://api/{%v%}/end'],
	],
]);
Assert::same('https://api/x/end', $map['url']);

// selhání skutečného procesu zastaví běh
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'fail'],
			['type' => 'set', 'key' => 'nemeloBy', 'value' => 'x'],
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
		'in' => ['text' => 'a; rm -rf /tmp/neexistuje'],
		'out' => ['result' => 'v'],
	]],
]);
Assert::same('a; rm -rf /tmp/neexistuje', $map['v']);

FileSystem::delete(TEMP_DIR);
