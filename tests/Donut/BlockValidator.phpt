<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Format\Input;
use Donut\Format\StdinSpec;
use Donut\Template;
use Donut\Validator\BlockValidator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$validator = new BlockValidator;

// Čistý kámen nemá co hlásit.
$cisty = new Block(
	name: 'cisty',
	command: 'curl',
	args: [[Template::parse('-sS')], [Template::parse('{%url%}')]],
	inputs: ['url' => new Input(name: 'url')],
);

Assert::same([], $validator->validate($cisty)->getProblems());

// {%STDIN%} v args je chyba — stdin se plní kanálem, ne šablonou.
$sStdin = new Block(
	name: 'sStdin',
	command: 'cat',
	args: [[Template::parse('{%STDIN%}')]],
	stdin: new StdinSpec,
);

$problemy = $validator->validate($sStdin)->getProblems();
Assert::count(1, $problemy);
Assert::same(Donut\Validator\Problem::Error, $problemy[0]->severity);
Assert::contains('{%STDIN%}', $problemy[0]->message);

// Nedeklarovaná proměnná v args je překlep, ne nevyplněná hodnota.
$neznamy = new Block(
	name: 'neznamy',
	command: 'curl',
	args: [[Template::parse('{%chybi%}')]],
);

$problemy = $validator->validate($neznamy)->getProblems();
Assert::count(1, $problemy);
Assert::contains('chybi', $problemy[0]->message);

// Výchozí location je soubor kamene.
Assert::same('neznamy.json', $problemy[0]->location);

// Předaná location přebije výchozí — takhle ji použije Validator u kroku.
Assert::same(
	'card-dev.json:steps[3]',
	$validator->validate($neznamy, 'card-dev.json:steps[3]')->getProblems()[0]->location,
);

// Každá nedeklarovaná proměnná se hlásí jednou, i když je v args víckrát.
$dvakrat = new Block(
	name: 'dvakrat',
	command: 'echo',
	args: [[Template::parse('{%chybi%}')], [Template::parse('{%chybi%}')]],
);

Assert::count(1, $validator->validate($dvakrat)->getProblems());
