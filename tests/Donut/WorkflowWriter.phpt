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

// Smallest valid workflow: only required fields. steps are written even when empty.
Assert::same(
	['name' => 'bare', 'steps' => []],
	$writer->toArray(new Workflow(name: 'bare')),
);

// A run step with everything. timeout and allow_failure do not occur even
// once on a step in the reference workload — if the writer dropped them,
// a round-trip over it wouldn't catch that.
Assert::same(
	[
		'type' => 'run',
		'name' => 'named',
		'block' => 'jq',
		'in' => ['stdin' => '{%input%}', 'filter' => '.id'],
		'out' => ['stdout' => 'result', 'exit_code' => 'code'],
		'timeout' => 90,
		'allow_failure' => [0, 1],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(
			block: 'jq',
			in: ['stdin' => Template::parse('{%input%}'), 'filter' => Template::parse('.id')],
			out: ['stdout' => 'result', 'exit_code' => 'code'],
			timeout: 90,
			allowFailure: [0, 1],
			name: 'named',
		),
	]))['steps'][0],
);

// A run step without anything optional. allow_failure: null means "unset"
// and must not appear; that is different for a step than for a block.
Assert::same(
	['type' => 'run', 'block' => 'echo'],
	$writer->toArray(new Workflow(name: 'w', steps: [new RunStep(block: 'echo')]))['steps'][0],
);

// allow_failure: false on a step is a deliberate opt-out, not the default
// state — it must be written out.
Assert::same(
	['type' => 'run', 'block' => 'echo', 'allow_failure' => false],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(block: 'echo', allowFailure: false),
	]))['steps'][0],
);

// A set step. name on a set does not occur even once in the reference workload.
Assert::same(
	['type' => 'set', 'name' => 'named set', 'key' => 'key', 'value' => 'a {%b%}'],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new SetStep(key: 'key', value: Template::parse('a {%b%}'), name: 'named set'),
	]))['steps'][0],
);

// An if step with both branches and with right
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

// An if step without right and with an empty else branch. then is written
// even when empty, because the parser requires it; else is omitted.
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

// A foreach step, including a nested step
Assert::same(
	[
		'type' => 'foreach',
		'over' => '{%list%}',
		'as' => 'row',
		'steps' => [['type' => 'set', 'key' => 'x', 'value' => '{%row%}']],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new ForeachStep(
			over: Template::parse('{%list%}'),
			as: 'row',
			steps: [new SetStep(key: 'x', value: Template::parse('{%row%}'))],
		),
	]))['steps'][0],
);

// A nested foreach inside an if — a shape the spec explicitly asks to cover
// and which otherwise does not occur (neither the reference workload nor
// the rest of this fixture).
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
		'then' => [
			[
				'type' => 'foreach',
				'over' => '{%list%}',
				'as' => 'row',
				'steps' => [['type' => 'set', 'key' => 'x', 'value' => '{%row%}']],
			],
		],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(
			condition: new Condition(left: Template::parse('{%a%}'), op: 'not_empty'),
			then: [
				new ForeachStep(
					over: Template::parse('{%list%}'),
					as: 'row',
					steps: [new SetStep(key: 'x', value: Template::parse('{%row%}'))],
				),
			],
		),
	]))['steps'][0],
);

// array_map() preserves keys; steps is array<int, Step>, not a list. A gap
// after unset() (the natural way the GUI deletes a step) would encode as
// a JSON object instead of an array without array_values() in stepsToArray().
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

// Workflow header: key order and omission of empty inputs
Assert::same(
	[
		'name' => 'full',
		'description' => 'Description',
		'inputs' => [
			'a' => ['required' => true, 'description' => 'A-item'],
			'b' => ['required' => false, 'default' => 'x'],
		],
		'steps' => [],
	],
	$writer->toArray(new Workflow(
		name: 'full',
		inputs: [
			'a' => new Input(name: 'a', description: 'A-item'),
			'b' => new Input(name: 'b', required: false, default: 'x'),
		],
		description: 'Description',
	)),
);
