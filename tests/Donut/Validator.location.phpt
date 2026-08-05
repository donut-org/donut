<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Tvar location je smlouva s GUI: to si tutéž cestu skládá samo, aby vědělo,
// ke kterému kroku problém patří. Kdyby se tvar změnil, GUI by tiše přestalo
// problémy zobrazovat — proto se připíná tady, ne tam.

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

// Krok s neplatným jménem klíče dá problém právě u toho kroku, ve kterém je —
// proto se jím dá tvar cesty proměřit ve všech čtyřech pozicích naráz.
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
			'as' => 'radek',
			'steps' => [['type' => 'set', 'key' => 'G-H', 'value' => 'x']],
		],
	],
]);

Assert::contains('w.json:steps[0]', $found);
Assert::contains('w.json:steps[1].then[0]', $found);
Assert::contains('w.json:steps[1].else[0]', $found);
Assert::contains('w.json:steps[2].steps[0]', $found);

// Problém, který nepatří žádnému kroku, má cestu bez dvojtečky — GUI podle
// toho pozná, že ho má vypsat u workflow, ne u kroku.
$workflowLevel = $locations([
	'name' => 'w',
	'inputs' => ['nepouzity' => []],
	'steps' => [],
]);

Assert::same(['w.json'], $workflowLevel);
