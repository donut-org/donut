<?php

declare(strict_types=1);

use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\Input;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Workflow;
use Donut\Template;
use Donut\Writer\WorkflowWriter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$writer = new WorkflowWriter;

// Nejmenší platné workflow: jen povinná pole. steps se vypisují i prázdné.
Assert::same(
	['name' => 'holy', 'steps' => []],
	$writer->toArray(new Workflow(name: 'holy')),
);

// Krok run se vším. timeout a allow_failure se v referenční zátěži
// u kroku nevyskytují ani jednou — kdyby je zapisovač zahodil,
// round-trip nad ní by to nepoznal.
Assert::same(
	[
		'type' => 'run',
		'name' => 'pojmenovaný',
		'block' => 'jq',
		'in' => ['stdin' => '{%vstup%}', 'filter' => '.id'],
		'out' => ['result' => 'vysledek', 'exit_code' => 'kod'],
		'timeout' => 90,
		'allow_failure' => [0, 1],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(
			block: 'jq',
			in: ['stdin' => Template::parse('{%vstup%}'), 'filter' => Template::parse('.id')],
			out: ['result' => 'vysledek', 'exit_code' => 'kod'],
			timeout: 90,
			allowFailure: [0, 1],
			name: 'pojmenovaný',
		),
	]))['steps'][0],
);

// Krok run bez ničeho volitelného. allow_failure: null znamená
// „nenastaveno" a nesmí se objevit; u kroku je to jiné než u kamene.
Assert::same(
	['type' => 'run', 'block' => 'echo'],
	$writer->toArray(new Workflow(name: 'w', steps: [new RunStep(block: 'echo')]))['steps'][0],
);

// allow_failure: false u kroku je vědomé vypnutí, ne výchozí stav —
// musí se vypsat.
Assert::same(
	['type' => 'run', 'block' => 'echo', 'allow_failure' => false],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(block: 'echo', allowFailure: false),
	]))['steps'][0],
);

// Krok set. name u setu se v referenční zátěži nevyskytuje ani jednou.
Assert::same(
	['type' => 'set', 'name' => 'pojmenovaný set', 'key' => 'klic', 'value' => 'a {%b%}'],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new SetStep(key: 'klic', value: Template::parse('a {%b%}'), name: 'pojmenovaný set'),
	]))['steps'][0],
);

// Krok if s oběma větvemi a s right
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '{%b%}'],
		'then' => [['type' => 'set', 'key' => 'x', 'value' => '1']],
		'else' => [['type' => 'set', 'key' => 'x', 'value' => '2']],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(
			condition: new Condition(
				left: Template::parse('{%a%}'),
				op: 'eq',
				right: Template::parse('{%b%}'),
			),
			then: [new SetStep(key: 'x', value: Template::parse('1'))],
			else: [new SetStep(key: 'x', value: Template::parse('2'))],
		),
	]))['steps'][0],
);

// Krok if bez right a s prázdnou větví else. then se vypisuje i prázdné,
// protože ho parser vyžaduje; else se vynechá.
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
		'then' => [],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(condition: new Condition(left: Template::parse('{%a%}'), op: 'not_empty')),
	]))['steps'][0],
);

// Krok foreach včetně vnořeného kroku
Assert::same(
	[
		'type' => 'foreach',
		'over' => '{%seznam%}',
		'as' => 'radek',
		'steps' => [['type' => 'set', 'key' => 'x', 'value' => '{%radek%}']],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new ForeachStep(
			over: Template::parse('{%seznam%}'),
			as: 'radek',
			steps: [new SetStep(key: 'x', value: Template::parse('{%radek%}'))],
		),
	]))['steps'][0],
);

// Vnořený foreach uvnitř if — tvar, který spec výslovně žádá pokrýt
// a který se jinak (referenční zátěž ani zbytek téhle fixture) nevyskytuje.
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
		'then' => [
			[
				'type' => 'foreach',
				'over' => '{%seznam%}',
				'as' => 'radek',
				'steps' => [['type' => 'set', 'key' => 'x', 'value' => '{%radek%}']],
			],
		],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(
			condition: new Condition(left: Template::parse('{%a%}'), op: 'not_empty'),
			then: [
				new ForeachStep(
					over: Template::parse('{%seznam%}'),
					as: 'radek',
					steps: [new SetStep(key: 'x', value: Template::parse('{%radek%}'))],
				),
			],
		),
	]))['steps'][0],
);

// array_map() zachovává klíče; steps je array<int, Step>, ne list. Mezera po
// unset() (přirozený způsob, jak GUI smaže krok) by se bez array_values()
// v stepsToArray() zakódovala jako JSON objekt místo pole.
$steps = [
	new SetStep(key: 'a', value: Template::parse('1')),
	new SetStep(key: 'b', value: Template::parse('2')),
	new SetStep(key: 'c', value: Template::parse('3')),
];
unset($steps[1]);

Assert::same(
	[
		['type' => 'set', 'key' => 'a', 'value' => '1'],
		['type' => 'set', 'key' => 'c', 'value' => '3'],
	],
	$writer->toArray(new Workflow(name: 'w', steps: $steps))['steps'],
);

// Hlavička workflow: pořadí klíčů a vynechání prázdných inputs
Assert::same(
	[
		'name' => 'plne',
		'description' => 'Popis',
		'inputs' => [
			'a' => ['required' => true, 'description' => 'Áčko'],
			'b' => ['required' => false, 'default' => 'x'],
		],
		'steps' => [],
	],
	$writer->toArray(new Workflow(
		name: 'plne',
		inputs: [
			'a' => new Input(name: 'a', description: 'Áčko'),
			'b' => new Input(name: 'b', required: false, default: 'x'),
		],
		description: 'Popis',
	)),
);
