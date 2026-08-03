<?php

declare(strict_types=1);

use Donut\Parser\BlockParser;
use Donut\Parser\ParseException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parser = new BlockParser;

$assertFails = function (array $data, string $message) use ($parser): void {
	Assert::exception(
		fn() => $parser->parseArray($data, 'x.json'),
		ParseException::class,
		$message
	);
};

$assertFails(
	['command' => 'x', 'args' => []],
	"x.json: klíč 'name' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'x', 'args' => []],
	"x.json: klíč 'command' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'x', 'command' => 'x'],
	"x.json: klíč 'args' je povinný a musí být pole."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => ['-v']],
	'x.json: args[0] musí být pole řetězců.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [[1]]],
	'x.json: args[0][0] musí být řetězec.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => 'ano'],
	'x.json: allow_failure musí být true, false, nebo pole celých čísel.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => ['a']],
	'x.json: allow_failure jako pole musí obsahovat jen celá čísla.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'timeout' => -1],
	"x.json: 'timeout' musí být kladné celé číslo."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'timeout' => 0],
	"x.json: 'timeout' musí být kladné celé číslo."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'outputs' => []],
	"x.json: neznámý klíč 'outputs'."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'inputs' => ['a' => 'ne']],
	"x.json: vstup 'a' musí být objekt."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'description' => ['a', 'b']],
	'x.json: description musí být řetězec.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'inputs' => ['url' => ['default' => ['a', 'b']]]],
	"x.json: default vstupu 'url' musí být řetězec."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'stdin' => ['required' => true, 'optional' => false]],
	"x.json: stdin má neznámý klíč 'optional'."
);
