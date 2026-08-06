<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Množiny klíčů vydává Result kvůli GUI: to si tutéž mapu odvozuje vlastním
// průchodem stromem a spojovací test v gui/ porovnává obojí. Bez toho by se
// ty dva průchody mohly rozejít a obě strany by zůstaly zelené.

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
	'inputs' => ['vstup' => []],
	'steps' => [
		// zápis přes set, čtení vstupu
		['type' => 'set', 'key' => 'zeSetu', 'value' => '{%vstup%}'],
		// zápis přes out, čtení klíče ze setu
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => '{%zeSetu%}'],
			'out' => ['result' => 'zVystupu'],
		],
		// zápis přes foreach.as, čtení v over
		[
			'type' => 'foreach',
			'over' => '{%zVystupu%}',
			'as' => 'radek',
			'steps' => [
				['type' => 'run', 'block' => 'echo', 'in' => ['text' => '{%radek%}']],
			],
		],
	],
], 'w.json');

$result = $validator->validate($workflow);

// Zapisuje se ze všech tří míst, odkud zápis vzniká.
Assert::same(['radek', 'zVystupu', 'zeSetu'], $result->getWrittenKeys());

// Čte se ze šablon v set.value, run.in a foreach.over.
Assert::same(['radek', 'vstup', 'zVystupu', 'zeSetu'], $result->getReadKeys());

// Workflow bez jediného kroku má obě množiny prázdné, ne null.
$prazdne = $validator->validate($parser->parseArray(
	['name' => 'w', 'steps' => []],
	'w.json',
));

Assert::same([], $prazdne->getWrittenKeys());
Assert::same([], $prazdne->getReadKeys());

// Klíč složený jen z číslic (I2): array_keys() by "456" tiše zkonvertovalo
// na int, GUI ho pak porovnává jako string z URL a nikdy by nesedělo.
$cislo = $validator->validate($parser->parseArray([
	'name' => 'w',
	'inputs' => [],
	'steps' => [['type' => 'set', 'key' => '456', 'value' => 'x']],
], 'w.json'));

Assert::same(['456'], $cislo->getWrittenKeys());
