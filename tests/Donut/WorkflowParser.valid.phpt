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
	'description' => 'Demo.',
	'inputs' => [
		'env' => ['required' => true],
		'tag' => ['required' => false, 'default' => 'latest'],
	],
	'steps' => [
		[
			'type' => 'run',
			'name' => 'download',
			'block' => 'curl-get',
			'in' => ['url' => 'https://x/{%env%}'],
			'out' => ['stdout' => 'body', 'exit_code' => 'rc'],
			'timeout' => 5,
			'allow_failure' => [0, 1],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%rc%}', 'op' => 'eq', 'right' => '0'],
			'then' => [
				['type' => 'set', 'key' => 'ok', 'value' => 'yes'],
			],
			'else' => [
				['type' => 'set', 'key' => 'ok', 'value' => 'no'],
			],
		],
		[
			'type' => 'foreach',
			'over' => '{%body%}',
			'as' => 'line',
			'steps' => [
				['type' => 'set', 'key' => 'last', 'value' => '{%line%}'],
			],
		],
	],
], 'demo.json');

Assert::same('demo', $wf->name);
Assert::same('Demo.', $wf->description);
Assert::same(['env', 'tag'], array_keys($wf->inputs));
Assert::same('latest', $wf->inputs['tag']->default);
Assert::count(3, $wf->steps);

$run = $wf->steps[0];
Assert::type(RunStep::class, $run);
Assert::same('download', $run->name);
Assert::same('curl-get', $run->block);
Assert::same(['url'], array_keys($run->in));
Assert::same(['env'], $run->in['url']->getKeys());
Assert::same(['stdout' => 'body', 'exit_code' => 'rc'], $run->out);
Assert::same(5, $run->timeout);
Assert::same([0, 1], $run->allowFailure);

$if = $wf->steps[1];
Assert::type(IfStep::class, $if);
Assert::same('eq', $if->condition->op);
Assert::same(['rc'], $if->condition->left->getKeys());
Assert::same('0', $if->condition->right?->getSource());
Assert::count(1, $if->then);
Assert::count(1, $if->else);
Assert::type(SetStep::class, $if->then[0]);
Assert::same('ok', $if->then[0]->key);

$each = $wf->steps[2];
Assert::type(ForeachStep::class, $each);
Assert::same(['body'], $each->over->getKeys());
Assert::same('line', $each->as);
Assert::count(1, $each->steps);

// minimal run step: without in, out, name
$wf = (new WorkflowParser)->parseArray([
	'name' => 'min',
	'steps' => [['type' => 'run', 'block' => 'x']],
], 'min.json');

Assert::same([], $wf->inputs);
Assert::same([], $wf->steps[0]->in);
Assert::same([], $wf->steps[0]->out);
Assert::null($wf->steps[0]->name);
Assert::null($wf->steps[0]->allowFailure);

// empty / not_empty doesn't need to have right
$wf = (new WorkflowParser)->parseArray([
	'name' => 'e',
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
		'then' => [],
	]],
], 'e.json');

Assert::null($wf->steps[0]->condition->right);
Assert::same([], $wf->steps[0]->then);
Assert::same([], $wf->steps[0]->else);
