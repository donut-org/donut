<?php

declare(strict_types=1);

use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Parser\WorkflowParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$wf = (new WorkflowParser)->parseArray([
	'name' => 'demo',
	'description' => 'Ukázka.',
	'inputs' => [
		'ENV' => ['required' => true],
		'TAG' => ['required' => false, 'default' => 'latest'],
	],
	'steps' => [
		[
			'type' => 'run',
			'name' => 'stáhnout',
			'block' => 'curl-get',
			'in' => ['URL' => 'https://x/{%ENV%}'],
			'out' => ['result' => 'BODY', 'exit_code' => 'RC'],
			'timeout' => 5,
			'allow_failure' => [0, 1],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%RC%}', 'op' => 'eq', 'right' => '0'],
			'then' => [
				['type' => 'set', 'key' => 'OK', 'value' => 'ano'],
			],
			'else' => [
				['type' => 'set', 'key' => 'OK', 'value' => 'ne'],
			],
		],
		[
			'type' => 'foreach',
			'over' => '{%BODY%}',
			'as' => 'LINE',
			'steps' => [
				['type' => 'set', 'key' => 'LAST', 'value' => '{%LINE%}'],
			],
		],
	],
], 'demo.json');

Assert::same('demo', $wf->name);
Assert::same('Ukázka.', $wf->description);
Assert::same(['ENV', 'TAG'], array_keys($wf->inputs));
Assert::same('latest', $wf->inputs['TAG']->default);
Assert::count(3, $wf->steps);

$run = $wf->steps[0];
Assert::type(RunStep::class, $run);
Assert::same('stáhnout', $run->name);
Assert::same('curl-get', $run->block);
Assert::same(['URL'], array_keys($run->in));
Assert::same(['ENV'], $run->in['URL']->getKeys());
Assert::same(['result' => 'BODY', 'exit_code' => 'RC'], $run->out);
Assert::same(5, $run->timeout);
Assert::same([0, 1], $run->allowFailure);

$if = $wf->steps[1];
Assert::type(IfStep::class, $if);
Assert::same('eq', $if->condition->op);
Assert::same(['RC'], $if->condition->left->getKeys());
Assert::same('0', $if->condition->right?->getSource());
Assert::count(1, $if->then);
Assert::count(1, $if->else);
Assert::type(SetStep::class, $if->then[0]);
Assert::same('OK', $if->then[0]->key);

$each = $wf->steps[2];
Assert::type(ForeachStep::class, $each);
Assert::same(['BODY'], $each->over->getKeys());
Assert::same('LINE', $each->as);
Assert::count(1, $each->steps);

// minimální run krok: bez in, out, name
$wf = (new WorkflowParser)->parseArray([
	'name' => 'min',
	'steps' => [['type' => 'run', 'block' => 'x']],
], 'min.json');

Assert::same([], $wf->inputs);
Assert::same([], $wf->steps[0]->in);
Assert::same([], $wf->steps[0]->out);
Assert::null($wf->steps[0]->name);
Assert::null($wf->steps[0]->allowFailure);

// empty / not_empty nemusí mít right
$wf = (new WorkflowParser)->parseArray([
	'name' => 'e',
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%A%}', 'op' => 'not_empty'],
		'then' => [],
	]],
], 'e.json');

Assert::null($wf->steps[0]->condition->right);
Assert::same([], $wf->steps[0]->then);
Assert::same([], $wf->steps[0]->else);
