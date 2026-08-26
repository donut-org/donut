<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo',
	'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$validator = new Validator($repo);

$errors = function (array $data) use ($parser, $validator): array {
	return array_map(strval(...), $validator->validate($parser->parseArray($data, 'w.json'))->getErrors());
};

$warnings = function (array $data) use ($parser, $validator): array {
	return array_map(strval(...), $validator->validate($parser->parseArray($data, 'w.json'))->getWarnings());
};

// key written earlier, read later
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'a', 'value' => 'x'],
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
	],
]));

// STDIN and CWD are known from the start
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%CWD%}/{%STDIN%}']]],
]));

// a key that no one ever writes = typo
Assert::same(
	['w.json:steps[0]: template reads key "missing", which no step writes'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%missing%}']]],
	])
);

// key written only later
Assert::same(
	['w.json:steps[0]: template reads key "a", which cannot have been created at this point'],
	$errors([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
			['type' => 'set', 'key' => 'a', 'value' => 'x'],
		],
	])
);

// a write in an if branch is only "maybe" after the if -> warning
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'a', 'value' => 'x']],
		],
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
	],
]));

Assert::contains(
	'w.json:steps[1]: template reads key "a", which may not exist',
	$warnings([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'a', 'value' => 'x']],
			],
			['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
		],
	])
);

// inside a branch, a write from that same branch is known
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
		'then' => [
			['type' => 'set', 'key' => 'a', 'value' => 'x'],
			['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
		],
	]],
]));

// foreach: as is known inside the body, only maybe after the loop
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [[
		'type' => 'foreach',
		'over' => '{%t%}',
		'as' => 'line',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%line%}']]],
	]],
]));

// a condition reads strictly: "maybe" is not enough
Assert::same(
	['w.json:steps[1]: condition reads key "a", which is created only on some paths — it must not decide which steps run'],
	$errors([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'a', 'value' => 'x']],
			],
			[
				'type' => 'if',
				'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 'x'],
				'then' => [],
			],
		],
	])
);

// foreach.over reads strictly
Assert::same(
	['w.json:steps[0]: foreach reads key "missing", which no step writes'],
	$errors([
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%missing%}',
			'as' => 'l',
			'steps' => [],
		]],
	])
);

// set may read its own key when it already exists
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'a', 'value' => 'x'],
		['type' => 'set', 'key' => 'a', 'value' => '{%a%} y'],
	],
]));

// warning: key is written and never read
Assert::contains(
	'w.json: key "unused" is written and never read',
	$warnings([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'unused', 'value' => 'x']],
	])
);

// warning: workflow input is never used
Assert::contains(
	'w.json: input "unused" is never used',
	$warnings([
		'name' => 'w',
		'inputs' => ['unused' => []],
		'steps' => [],
	])
);

// unused STDIN and CWD do not warn
Assert::same([], $warnings([
	'name' => 'w',
	'steps' => [],
]));

// then and else are alternatives: a key written only in then cannot exist in else
Assert::same(
	['w.json:steps[0].else[0]: template reads key "a", which cannot have been created at this point'],
	$errors([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'a', 'value' => 'x']],
			'else' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']]],
		]],
	])
);

// and symmetrically: a key written only in else cannot exist in then
Assert::same(
	['w.json:steps[0].then[0]: template reads key "b", which cannot have been created at this point'],
	$errors([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%b%}']]],
			'else' => [['type' => 'set', 'key' => 'b', 'value' => 'x']],
		]],
	])
);

// set reads a key that no one ever writes
Assert::same(
	['w.json:steps[0]: set reads key "missing", which no step writes'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'a', 'value' => '{%missing%}']],
	])
);

// set reads a key written only in an if branch -> warning
Assert::contains(
	'w.json:steps[1]: set reads key "a", which may not exist',
	$warnings([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'a', 'value' => 'x']],
			],
			['type' => 'set', 'key' => 'b', 'value' => '{%a%}'],
		],
	])
);

// a key written in both branches of an if is known after it, not just "maybe"
Assert::same([], $warnings([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'v', 'value' => 'a']],
			'else' => [['type' => 'set', 'key' => 'v', 'value' => 'b']],
		],
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%v%}']],
	],
]));

// and the same key, read strictly (by a condition), must not be an error
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'v', 'value' => 'a']],
			'else' => [['type' => 'set', 'key' => 'v', 'value' => 'b']],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%v%}', 'op' => 'eq', 'right' => 'a'],
			'then' => [],
		],
	],
]));

Nette\Utils\FileSystem::delete(TEMP_DIR);
