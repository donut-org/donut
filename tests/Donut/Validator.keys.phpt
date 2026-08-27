<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Result exposes the key sets for the GUI: it derives the same map with its
// own tree traversal, and a joining test in gui/ compares the two. Without
// this, the two traversals could drift apart and both sides would stay
// green.

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

$workflow = $parser->parseArray([
	'name' => 'w',
	'inputs' => ['input' => []],
	'steps' => [
		// write via set, read of the input
		['type' => 'set', 'key' => 'fromSet', 'value' => '{%input%}'],
		// write via out, read of the key from set
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => '{%fromSet%}'],
			'out' => ['stdout' => 'fromOutput'],
		],
		// write via foreach.as, read in over
		[
			'type' => 'foreach',
			'over' => '{%fromOutput%}',
			'as' => 'row',
			'steps' => [
				['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%row%}']],
			],
		],
	],
], 'w.json');

$result = $validator->validate($workflow);

// Writes come from all three places a write can arise.
Assert::same(['fromOutput', 'fromSet', 'row'], $result->getWrittenKeys());

// Reads come from templates in set.value, run.in and foreach.over.
Assert::same(['fromOutput', 'fromSet', 'input', 'row'], $result->getReadKeys());

// A workflow without a single step has both sets empty, not null.
$empty = $validator->validate($parser->parseArray(
	['name' => 'w', 'steps' => []],
	'w.json',
));

Assert::same([], $empty->getWrittenKeys());
Assert::same([], $empty->getReadKeys());

// A key made up of only digits (I2): array_keys() would silently convert
// "456" to int, and the GUI then compares it as a string from the URL,
// which would never match.
$number = $validator->validate($parser->parseArray([
	'name' => 'w',
	'inputs' => [],
	'steps' => [['type' => 'set', 'key' => '456', 'value' => 'x']],
], 'w.json'));

Assert::same(['456'], $number->getWrittenKeys());
