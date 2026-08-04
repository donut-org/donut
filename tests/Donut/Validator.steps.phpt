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
	'args' => [['{%text%}'], ['{%suffix%}']],
	'inputs' => [
		'text' => ['required' => true],
		'suffix' => ['required' => false],
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
	'args' => [['{%a%}']],
	'inputs' => ['a' => ['required' => true, 'default' => 'x']],
]));

file_put_contents($dir . '/badStdinArg.json', json_encode([
	'name' => 'badStdinArg',
	'command' => 'echo',
	'args' => [['{%STDIN%}']],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/badArgsInput.json', json_encode([
	'name' => 'badArgsInput',
	'command' => 'echo',
	'args' => [['--tag={%tga%}']],
	'inputs' => ['tag' => ['required' => true]],
]));

file_put_contents($dir . '/stdinInput.json', json_encode([
	'name' => 'stdinInput',
	'command' => 'cat',
	'args' => [],
	'inputs' => ['stdin' => ['required' => true]],
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
	'inputs' => ['t' => ['required' => true]],
	'steps' => [
		['type' => 'run', 'block' => 'greet', 'in' => ['text' => '{%t%}']],
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
	['w.json:steps[0]: povinný vstup "text" kamene "greet" není naplněn'],
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
	['w.json:steps[0]: kámen "greet" nedeklaruje vstup "neznamy"'],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['text' => '{%t%}', 'neznamy' => 'x'],
		]],
	])
);

// stdin u kamene, který stdin nemá
Assert::same(
	['w.json:steps[0]: kámen "greet" nečte stdin, ale krok ho plní'],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['text' => '{%t%}', 'stdin' => 'x'],
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
			'in' => ['stdin' => 'x'],
		]],
	])
);

// args kamene odkazuje proměnnou, kterou kámen nedeklaruje jako vstup
Assert::same(
	['w.json:steps[0]: kámen "badArgsInput" používá v args proměnnou "tga", kterou nedeklaruje'],
	$messages([
		'name' => 'w',
		'steps' => [[
			'type' => 'run',
			'block' => 'badArgsInput',
			'in' => ['tag' => 'v1'],
		]],
	])
);

// neznámý kanál v out
Assert::same(
	['w.json:steps[0]: neznámý kanál "stdout"'],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['text' => '{%t%}'],
			'out' => ['stdout' => 'x'],
		]],
	])
);

// neznámý operátor
Assert::same(
	['w.json:steps[0]: neznámý operátor "matches"'],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'matches', 'right' => 'x'],
			'then' => [],
		]],
	])
);

// binární operátor bez right
Assert::same(
	['w.json:steps[0]: operátor "eq" vyžaduje \'right\''],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'eq'],
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
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%t%}',
			'as' => 'A B',
			'steps' => [],
		]],
	])
);

// kámen nesmí deklarovat vstup jménem stdin — je to jméno kanálu, ne klíč mapy,
// a u kroku by nešlo poznat, jestli "in": { "stdin": … } plní vstup, nebo kanál
Assert::contains(
	'w.json:steps[0]: kámen "stdinInput" nesmí mít vstup jménem "stdin" — je to jméno kanálu',
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'stdinInput']],
	])
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
