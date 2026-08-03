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
	"w.json: klíč 'name' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'w'],
	"w.json: klíč 'steps' je povinný a musí být pole."
);

$assertFails(
	['name' => 'w', 'steps' => [[]]],
	"w.json: steps[0] nemá klíč 'type'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'while']]],
	"w.json: steps[0] má neznámý typ kroku 'while'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run']]],
	"w.json: steps[0] nemá klíč 'block'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'key' => 'A']]],
	"w.json: steps[0] nemá klíč 'value'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'over' => '{%A%}', 'as' => 'B']]],
	"w.json: steps[0] nemá klíč 'steps'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['op' => 'eq'], 'then' => []]],
	],
	"w.json: steps[0].condition nemá 'left'."
);

// chyba ve vnořeném kroku ukazuje na správné místo
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1'],
			'then' => [['type' => 'run']],
		]],
	],
	"w.json: steps[0].then[0] nemá klíč 'block'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%A%}',
			'as' => 'B',
			'steps' => [['type' => 'set', 'key' => 'C']],
		]],
	],
	"w.json: steps[0].steps[0] nemá klíč 'value'."
);

// chyba ve vnořeném kroku uvnitř 'else' ukazuje na správné místo
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'else' => [['type' => 'run']],
		]],
	],
	"w.json: steps[0].else[0] nemá klíč 'block'."
);

$assertFails(
	['name' => 'w', 'steps' => [], 'extra' => 1],
	"w.json: neznámý klíč 'extra'."
);

$assertFails(
	['name' => 'w', 'steps' => ['not-an-object']],
	'w.json: steps[0] musí být objekt.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'in' => 'nope']]],
	'w.json: steps[0].in musí být objekt.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'out' => 'nope']]],
	'w.json: steps[0].out musí být objekt.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'in' => ['URL' => 5]]]],
	'w.json: steps[0].in musí být objekt řetězec => řetězec.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'out' => ['result' => 5]]]],
	'w.json: steps[0].out musí být objekt řetězec => řetězec.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'timeout' => -1]]],
	'w.json: steps[0].timeout musí být kladné celé číslo.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'timeout' => 0]]],
	'w.json: steps[0].timeout musí být kladné celé číslo.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'if']]],
	"w.json: steps[0] nemá klíč 'condition'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['left' => '{%A%}'], 'then' => []]],
	],
	"w.json: steps[0].condition nemá 'op'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1']]],
	],
	"w.json: steps[0] nemá klíč 'then'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'else' => 'nope',
		]],
	],
	'w.json: steps[0].else musí být pole.'
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 5],
			'then' => [],
		]],
	],
	'w.json: steps[0].condition.right musí být řetězec.'
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'as' => 'B', 'steps' => []]]],
	"w.json: steps[0] nemá klíč 'over'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'over' => '{%A%}', 'steps' => []]]],
	"w.json: steps[0] nemá klíč 'as'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'value' => 'x']]],
	"w.json: steps[0] nemá klíč 'key'."
);

// neznámý klíč uvnitř kroku 'run'
$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run', 'block' => 'x', 'outs' => []]]],
	"w.json: steps[0] má neznámý klíč 'outs'."
);

// neznámý klíč uvnitř kroku 'if' — typický překlep 'esle'
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1'],
			'then' => [],
			'esle' => [],
		]],
	],
	"w.json: steps[0] má neznámý klíč 'esle'."
);

// neznámý klíč uvnitř kroku 'set'
$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'key' => 'A', 'value' => 'x', 'default' => 'y']]],
	"w.json: steps[0] má neznámý klíč 'default'."
);

// neznámý klíč uvnitř kroku 'foreach'
$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'foreach', 'over' => '{%A%}', 'as' => 'B', 'steps' => [], 'in' => []]],
	],
	"w.json: steps[0] má neznámý klíč 'in'."
);

// neznámý klíč uvnitř 'condition'
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1', 'nope' => true],
			'then' => [],
		]],
	],
	"w.json: steps[0].condition má neznámý klíč 'nope'."
);

// neznámý klíč uvnitř 'inputs.<name>'
$assertFails(
	['name' => 'w', 'inputs' => ['T' => ['requried' => true]], 'steps' => []],
	"w.json: vstup 'T' má neznámý klíč 'requried'."
);
