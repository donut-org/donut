<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/greet.json', json_encode([
	'name' => 'greet',
	'command' => 'echo',
	'args' => [['{%TEXT%}'], ['{%SUFFIX%}']],
	'inputs' => [
		'TEXT' => ['required' => true],
		'SUFFIX' => ['required' => false],
	],
]));

file_put_contents($dir . '/withStdin.json', json_encode([
	'name' => 'withStdin',
	'command' => 'cat',
	'args' => [],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/withDefault.json', json_encode([
	'name' => 'withDefault',
	'command' => 'echo',
	'args' => [['{%A%}']],
	'inputs' => ['A' => ['required' => true, 'default' => 'x']],
]));

file_put_contents($dir . '/badStdinArg.json', json_encode([
	'name' => 'badStdinArg',
	'command' => 'echo',
	'args' => [['{%STDIN%}']],
	'stdin' => ['required' => true],
]));

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$validator = new Validator($repo);

/** @return list<string> */
$messages = function (array $data) use ($parser, $validator): array {
	$result = $validator->validate($parser->parseArray($data, 'w.json'));
	return array_map(strval(...), $result->getErrors());
};

// všechno v pořádku
Assert::same([], $messages([
	'name' => 'w',
	'inputs' => ['T' => ['required' => true]],
	'steps' => [
		['type' => 'run', 'block' => 'greet', 'in' => ['TEXT' => '{%T%}']],
	],
]));

// kámen neexistuje
Assert::same(
	['w.json:steps[0]: kámen "chybi" neexistuje'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'chybi']],
	])
);

// povinný vstup není naplněn
Assert::same(
	['w.json:steps[0]: povinný vstup "TEXT" kamene "greet" není naplněn'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'greet']],
	])
);

// povinný vstup s defaultem naplněn být nemusí
Assert::same([], $messages([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'withDefault']],
]));

// in obsahuje jméno, které kámen nedeklaruje
Assert::same(
	['w.json:steps[0]: kámen "greet" nedeklaruje vstup "NEZNAMY"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}', 'NEZNAMY' => 'x'],
		]],
	])
);

// STDIN u kamene, který stdin nemá
Assert::same(
	['w.json:steps[0]: kámen "greet" nečte stdin, ale krok ho plní'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}', 'STDIN' => 'x'],
		]],
	])
);

// povinný stdin není naplněn
Assert::same(
	['w.json:steps[0]: kámen "withStdin" vyžaduje stdin, krok ho neplní'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'withStdin']],
	])
);

// {%STDIN%} v args kamene
Assert::same(
	['w.json:steps[0]: {%STDIN%} použito v args kamene "badStdinArg"'],
	$messages([
		'name' => 'w',
		'steps' => [[
			'type' => 'run',
			'block' => 'badStdinArg',
			'in' => ['STDIN' => 'x'],
		]],
	])
);

// neznámý kanál v out
Assert::same(
	['w.json:steps[0]: neznámý kanál "stdout"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}'],
			'out' => ['stdout' => 'X'],
		]],
	])
);

// neznámý operátor
Assert::same(
	['w.json:steps[0]: neznámý operátor "matches"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'matches', 'right' => 'x'],
			'then' => [],
		]],
	])
);

// klíč, na který by pak nešlo odkázat
Assert::same(
	['w.json:steps[0]: klíč "A-B" není platné jméno'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'A-B', 'value' => 'x']],
	])
);

Assert::same(
	['w.json:steps[0]: klíč "A B" není platné jméno'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%T%}',
			'as' => 'A B',
			'steps' => [],
		]],
	])
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
