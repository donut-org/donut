<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// The shape of location is a contract with the GUI: it builds the same path
// on its own to know which step a problem belongs to. If the shape changed,
// the GUI would silently stop showing problems — hence it is pinned here,
// not there.

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$validator = new Validator($repo);

/** @return list<string> */
$locations = function (array $data) use ($parser, $validator): array {
	$result = $validator->validate($parser->parseArray($data, 'w.json'));
	return array_map(fn($p) => $p->location, $result->getProblems());
};

// A step with an invalid key name gives a problem right at the step it is
// in — so it can be used to measure the path shape at all four positions at once.
$found = $locations([
	'name' => 'w',
	'inputs' => ['t' => []],
	'steps' => [
		['type' => 'set', 'key' => 'A-B', 'value' => 'x'],
		[
			'type' => 'if',
			'condition' => ['left' => '{%t%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'C-D', 'value' => 'x']],
			'else' => [['type' => 'set', 'key' => 'E-F', 'value' => 'x']],
		],
		[
			'type' => 'foreach',
			'over' => '{%t%}',
			'as' => 'row',
			'steps' => [['type' => 'set', 'key' => 'G-H', 'value' => 'x']],
		],
	],
]);

Assert::contains('w.json:steps[0]', $found);
Assert::contains('w.json:steps[1].then[0]', $found);
Assert::contains('w.json:steps[1].else[0]', $found);
Assert::contains('w.json:steps[2].steps[0]', $found);

// A problem that does not belong to any step has a path with no colon — the
// GUI uses that to tell it should list it under the workflow, not a step.
$workflowLevel = $locations([
	'name' => 'w',
	'inputs' => ['unused' => []],
	'steps' => [],
]);

Assert::same(['w.json'], $workflowLevel);
