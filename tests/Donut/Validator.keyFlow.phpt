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

// klíč zapsaný dřív, čtený později
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'a', 'value' => 'x'],
		['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
	],
]));

// STDIN a CWD jsou známé od začátku
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%CWD%}/{%STDIN%}']]],
]));

// klíč, který nikdo nikdy nezapisuje = překlep
Assert::same(
	['w.json:steps[0]: šablona čte klíč "neni", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%neni%}']]],
	])
);

// klíč zapsaný až později
Assert::same(
	['w.json:steps[0]: šablona čte klíč "a", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%a%}']],
			['type' => 'set', 'key' => 'a', 'value' => 'x'],
		],
	])
);

// zápis ve větvi if je za ifem jen "možná" -> varování
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
	'w.json:steps[1]: šablona čte klíč "a", který nemusí existovat',
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

// uvnitř větve je zápis z téže větve jistý
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

// foreach: as je uvnitř těla jistý, za cyklem jen možný
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

// podmínka čte přísně: "možná" nestačí
Assert::same(
	['w.json:steps[1]: podmínka čte klíč "a", který v tomto místě nemohl vzniknout'],
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

// foreach.over čte přísně
Assert::same(
	['w.json:steps[0]: foreach čte klíč "neni", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%neni%}',
			'as' => 'l',
			'steps' => [],
		]],
	])
);

// set smí číst vlastní klíč, když už existuje
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'a', 'value' => 'x'],
		['type' => 'set', 'key' => 'a', 'value' => '{%a%} y'],
	],
]));

// varování: klíč se zapisuje a nikdy nečte
Assert::contains(
	'w.json: klíč "nepouzity" se zapisuje a nikdy nečte',
	$warnings([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'nepouzity', 'value' => 'x']],
	])
);

// varování: vstup workflow se nikde nepoužívá
Assert::contains(
	'w.json: vstup "nepouzity" se nikde nepoužívá',
	$warnings([
		'name' => 'w',
		'inputs' => ['nepouzity' => []],
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
	['w.json:steps[0].else[0]: šablona čte klíč "a", který v tomto místě nemohl vzniknout'],
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

// a symetricky: klíč zapsaný jen v else nemůže existovat v then
Assert::same(
	['w.json:steps[0].then[0]: šablona čte klíč "b", který v tomto místě nemohl vzniknout'],
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

// set čte klíč, který nikdo nikdy nezapisuje
Assert::same(
	['w.json:steps[0]: set čte klíč "neni", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'a', 'value' => '{%neni%}']],
	])
);

// set čte klíč zapsaný jen ve větvi if -> varování
Assert::contains(
	'w.json:steps[1]: set čte klíč "a", který nemusí existovat',
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

// klíč zapsaný v obou větvích if je za ním jistý, ne jen "možná"
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

// a totéž čtené přísně (podmínkou) nesmí být chyba
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
