<?php

declare(strict_types=1);

use Donut\Parser\ParseException;
use Donut\Parser\WorkflowParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parser = new WorkflowParser;

$assertFails = function (array $data, string $message) use ($parser): void {
	Assert::exception(
		fn() => $parser->parseArray($data, 'w.json'),
		ParseException::class,
		$message
	);
};

$assertFails(
	['steps' => []],
	"w.json: key 'name' is required and must be a non-empty string."
);

$assertFails(
	['name' => 'w'],
	"w.json: key 'steps' is required and must be an array."
);

$assertFails(
	['name' => 'w', 'steps' => [[]]],
	"w.json: steps[0] has no 'type' key."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'while']]],
	"w.json: steps[0] has an unknown step type 'while'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run']]],
	"w.json: steps[0] has no 'block' key."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'key' => 'a']]],
	"w.json: steps[0] has no 'value' key."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'over' => '{%a%}', 'as' => 'b']]],
	"w.json: steps[0] has no 'steps' key."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['op' => 'eq'], 'then' => []]],
	],
	"w.json: steps[0].condition has no 'left'."
);

// an error in a nested step points to the right place
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1'],
			'then' => [['type' => 'run']],
		]],
	],
	"w.json: steps[0].then[0] has no 'block' key."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%a%}',
			'as' => 'b',
			'steps' => [['type' => 'set', 'key' => 'c']],
		]],
	],
	"w.json: steps[0].steps[0] has no 'value' key."
);

// an error in a nested step inside 'else' points to the right place
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'else' => [['type' => 'run']],
		]],
	],
	"w.json: steps[0].else[0] has no 'block' key."
);

$assertFails(
	['name' => 'w', 'steps' => [], 'extra' => 1],
	"w.json: unknown key 'extra'."
);

$assertFails(
	['name' => 'w', 'steps' => ['not-an-object']],
	'w.json: steps[0] must be an object.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'in' => 'nope']]],
	'w.json: steps[0].in must be an object.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'out' => 'nope']]],
	'w.json: steps[0].out must be an object.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'in' => ['url' => 5]]]],
	'w.json: steps[0].in must be an object of string => string.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'out' => ['result' => 5]]]],
	'w.json: steps[0].out must be an object of string => string.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'timeout' => -1]]],
	'w.json: steps[0].timeout must be a positive integer.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'timeout' => 0]]],
	'w.json: steps[0].timeout must be a positive integer.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'if']]],
	"w.json: steps[0] has no 'condition' key."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['left' => '{%a%}'], 'then' => []]],
	],
	"w.json: steps[0].condition has no 'op'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1']]],
	],
	"w.json: steps[0] has no 'then' key."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'else' => 'nope',
		]],
	],
	'w.json: steps[0].else must be an array.'
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => 5],
			'then' => [],
		]],
	],
	'w.json: steps[0].condition.right must be a string.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'as' => 'b', 'steps' => []]]],
	"w.json: steps[0] has no 'over' key."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'over' => '{%a%}', 'steps' => []]]],
	"w.json: steps[0] has no 'as' key."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'value' => 'x']]],
	"w.json: steps[0] has no 'key' key."
);

// unknown key inside a 'run' step
$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'outs' => []]]],
	"w.json: steps[0] has an unknown key 'outs'."
);

// unknown key inside an 'if' step — a typical typo 'esle'
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'esle' => [],
		]],
	],
	"w.json: steps[0] has an unknown key 'esle'."
);

// unknown key inside a 'set' step
$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'key' => 'a', 'value' => 'x', 'default' => 'y']]],
	"w.json: steps[0] has an unknown key 'default'."
);

// unknown key inside a 'foreach' step
$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'foreach', 'over' => '{%a%}', 'as' => 'b', 'steps' => [], 'in' => []]],
	],
	"w.json: steps[0] has an unknown key 'in'."
);

// unknown key inside 'condition'
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '1', 'nope' => true],
			'then' => [],
		]],
	],
	"w.json: steps[0].condition has an unknown key 'nope'."
);

// unknown key inside 'inputs.<name>'
$assertFails(
	['name' => 'w', 'inputs' => ['t' => ['requried' => true]], 'steps' => []],
	"w.json: input 't' has an unknown key 'requried'."
);
