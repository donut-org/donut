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
