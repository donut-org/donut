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

// a whole workflow: process -> map -> stdin of the next process -> if -> foreach
$map = $run([
	'name' => 'w',
	'inputs' => ['name' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => 'hi {%name%}'],
			'out' => ['stdout' => 'greeting'],
		],
		[
			'type' => 'run', 'block' => 'upper',
			'in' => ['stdin' => '{%greeting%}'],
			'out' => ['stdout' => 'loud'],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%loud%}', 'op' => 'contains', 'right' => 'WORLD'],
			'then' => [['type' => 'set', 'key' => 'who', 'value' => 'world']],
			'else' => [['type' => 'set', 'key' => 'who', 'value' => 'someone else']],
		],
	],
], ['name' => 'world']);

Assert::same('hi world', $map['greeting']);
Assert::same('HI WORLD', $map['loud']);
Assert::same('world', $map['who']);

// foreach over a real multi-line process output
$map = $run([
	'name' => 'w',
	'inputs' => ['lines' => []],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => '{%lines%}'],
			'out' => ['stdout' => 'list'],
		],
		[
			'type' => 'foreach', 'over' => '{%list%}', 'as' => 'r',
			'steps' => [['type' => 'set', 'key' => 'last', 'value' => 'line-{%r%}']],
		],
	],
], ['lines' => "first\nsecond\nthird"]);

Assert::same('line-third', $map['last']);

// trailing newlines are stripped, so the value goes straight into the argument
$map = $run([
	'name' => 'w',
	'steps' => [
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => 'x'], 'out' => ['stdout' => 'v']],
		['type' => 'set', 'key' => 'url', 'value' => 'https://api/{%v%}/end'],
	],
]);
Assert::same('https://api/x/end', $map['url']);

// a real process failure stops the run
Assert::exception(
	fn() => $run([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'fail'],
			['type' => 'set', 'key' => 'shouldNotHappen', 'value' => 'x'],
		],
	]),
	RunFailedException::class,
	'w.json:steps[0]: block "fail" finished with exit code 1.'
);

// arguments don't pass through the shell
$map = $run([
	'name' => 'w',
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['text' => 'a; rm -rf /tmp/missing'],
		'out' => ['stdout' => 'v'],
	]],
]);
Assert::same('a; rm -rf /tmp/missing', $map['v']);

FileSystem::delete(TEMP_DIR);
