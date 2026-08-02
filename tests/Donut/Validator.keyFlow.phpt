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
	'args' => [['{%TEXT%}']],
	'inputs' => ['TEXT' => ['required' => true]],
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

// klíč zapsaný dřív, čtený později
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'A', 'value' => 'x'],
		['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
	],
]));

// STDIN a CWD jsou známé od začátku
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%CWD%}/{%STDIN%}']]],
]));

// klíč, který nikdo nikdy nezapisuje = překlep
Assert::same(
	['w.json:steps[0]: šablona čte klíč "NENI", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%NENI%}']]],
	])
);

// klíč zapsaný až později
Assert::same(
	['w.json:steps[0]: šablona čte klíč "A", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
			['type' => 'set', 'key' => 'A', 'value' => 'x'],
		],
	])
);

// zápis ve větvi if je za ifem jen "možná" -> varování
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
		],
		['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
	],
]));

Assert::contains(
	'w.json:steps[1]: šablona čte klíč "A", který nemusí existovat',
	$warnings([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			],
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
		],
	])
);

// uvnitř větve je zápis z téže větve jistý
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
		'then' => [
			['type' => 'set', 'key' => 'A', 'value' => 'x'],
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
		],
	]],
]));

// foreach: as je uvnitř těla jistý, za cyklem jen možný
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [[
		'type' => 'foreach',
		'over' => '{%T%}',
		'as' => 'LINE',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%LINE%}']]],
	]],
]));

// podmínka čte přísně: "možná" nestačí
Assert::same(
	['w.json:steps[1]: podmínka čte klíč "A", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			],
			[
				'type' => 'if',
				'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'x'],
				'then' => [],
			],
		],
	])
);

// foreach.over čte přísně
Assert::same(
	['w.json:steps[0]: foreach čte klíč "NENI", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%NENI%}',
			'as' => 'L',
			'steps' => [],
		]],
	])
);

// set smí číst vlastní klíč, když už existuje
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'A', 'value' => 'x'],
		['type' => 'set', 'key' => 'A', 'value' => '{%A%} y'],
	],
]));

// varování: klíč se zapisuje a nikdy nečte
Assert::contains(
	'w.json: klíč "NEPOUZITY" se zapisuje a nikdy nečte',
	$warnings([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'NEPOUZITY', 'value' => 'x']],
	])
);

// varování: vstup workflow se nikde nepoužívá
Assert::contains(
	'w.json: vstup "NEPOUZITY" se nikde nepoužívá',
	$warnings([
		'name' => 'w',
		'inputs' => ['NEPOUZITY' => []],
		'steps' => [],
	])
);

// STDIN a CWD nepoužité nevarují
Assert::same([], $warnings([
	'name' => 'w',
	'steps' => [],
]));

// then a else jsou alternativy: klíč zapsaný jen v then nemůže existovat v else
Assert::same(
	['w.json:steps[0].else[0]: šablona čte klíč "A", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			'else' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']]],
		]],
	])
);

// a symetricky: klíč zapsaný jen v else nemůže existovat v then
Assert::same(
	['w.json:steps[0].then[0]: šablona čte klíč "B", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
			'then' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%B%}']]],
			'else' => [['type' => 'set', 'key' => 'B', 'value' => 'x']],
		]],
	])
);

// set čte klíč, který nikdo nikdy nezapisuje
Assert::same(
	['w.json:steps[0]: set čte klíč "NENI", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'A', 'value' => '{%NENI%}']],
	])
);

// set čte klíč zapsaný jen ve větvi if -> varování
Assert::contains(
	'w.json:steps[1]: set čte klíč "A", který nemusí existovat',
	$warnings([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			],
			['type' => 'set', 'key' => 'B', 'value' => '{%A%}'],
		],
	])
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
