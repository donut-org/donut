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
	"x.json: key 'name' is required and must be a non-empty string."
);

$assertFails(
	['name' => 'x', 'args' => []],
	"x.json: key 'command' is required and must be a non-empty string."
);

$assertFails(
	['name' => 'x', 'command' => 'x'],
	"x.json: key 'args' is required and must be an array."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => ['-v']],
	'x.json: args[0] must be an array of strings.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [[1]]],
	'x.json: args[0][0] must be a string.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => 'yes'],
	'x.json: allow_failure must be true, false, or an array of integers.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => ['a']],
	'x.json: allow_failure as an array must contain only integers.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'timeout' => -1],
	"x.json: 'timeout' must be a positive integer."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'timeout' => 0],
	"x.json: 'timeout' must be a positive integer."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'outputs' => []],
	"x.json: unknown key 'outputs'."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'inputs' => ['a' => 'no']],
	"x.json: input 'a' must be an object."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'description' => ['a', 'b']],
	'x.json: description must be a string.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'inputs' => ['url' => ['default' => ['a', 'b']]]],
	"x.json: default of input 'url' must be a string."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'stdin' => ['required' => true, 'optional' => false]],
	"x.json: stdin has an unknown key 'optional'."
);
