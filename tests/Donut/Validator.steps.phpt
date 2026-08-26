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

// everything is fine
Assert::same([], $messages([
	'name' => 'w',
	'inputs' => ['t' => ['required' => true]],
	'steps' => [
		['type' => 'run', 'block' => 'greet', 'in' => ['text' => '{%t%}']],
	],
]));

// block does not exist
Assert::same(
	['w.json:steps[0]: block "missing" does not exist'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'missing']],
	])
);

// required input is not filled
Assert::same(
	['w.json:steps[0]: required input "text" of block "greet" is not filled'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'greet']],
	])
);

// a required input with a default need not be filled
Assert::same([], $messages([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'withDefault']],
]));

// in contains a name the block does not declare
Assert::same(
	['w.json:steps[0]: block "greet" does not declare input "unknown"'],
	$messages([
		'name' => 'w',
		'inputs' => ['t' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['text' => '{%t%}', 'unknown' => 'x'],
		]],
	])
);

// stdin for a block that has no stdin
Assert::same(
	['w.json:steps[0]: block "greet" does not read stdin, but the step fills it'],
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

// required stdin is not filled
Assert::same(
	['w.json:steps[0]: block "withStdin" requires stdin, the step does not fill it'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'withStdin']],
	])
);

// {%STDIN%} in a block's args
Assert::same(
	['w.json:steps[0]: {%STDIN%} used in args of block "badStdinArg"'],
	$messages([
		'name' => 'w',
		'steps' => [[
			'type' => 'run',
			'block' => 'badStdinArg',
			'in' => ['stdin' => 'x'],
		]],
	])
);

// a block's args reference a variable the block does not declare as an input
Assert::same(
	['w.json:steps[0]: block "badArgsInput" uses variable "tga" in args without declaring it'],
	$messages([
		'name' => 'w',
		'steps' => [[
			'type' => 'run',
			'block' => 'badArgsInput',
			'in' => ['tag' => 'v1'],
		]],
	])
);

// unknown channel in out
Assert::same(
	['w.json:steps[0]: unknown channel "stdout"'],
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

// unknown operator
Assert::same(
	['w.json:steps[0]: unknown operator "matches"'],
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

// binary operator without right
Assert::same(
	['w.json:steps[0]: operator "eq" requires \'right\''],
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

// a key that could then not be referenced
Assert::same(
	['w.json:steps[0]: key "A-B" is not a valid name'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'A-B', 'value' => 'x']],
	])
);

Assert::same(
	['w.json:steps[0]: key "A B" is not a valid name'],
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

// a block must not declare an input named stdin — that is a channel name, not
// a map key, and a step could not tell whether "in": { "stdin": … } fills the
// input or the channel
Assert::contains(
	'w.json:steps[0]: block "stdinInput" must not have an input named "stdin" — that is a channel name',
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'stdinInput']],
	])
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
