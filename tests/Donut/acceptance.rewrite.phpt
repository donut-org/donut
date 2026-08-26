<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = __DIR__ . '/../../docs/workflows/donut';

$repo = new BlockRepository($root . '/blocks');
$validator = new Validator($repo);
$parser = new WorkflowParser;

// all blocks get loaded
Assert::count(15, $repo->getNames());

foreach ($repo->getNames() as $name) {
	Assert::same($name, $repo->get($name)->name);
}

// every workflow passes without errors and without warnings
$files = glob($root . '/workflows/*.json');
Assert::count(4, $files);

foreach ($files as $file) {
	$workflow = $parser->parseFile($file);
	$result = $validator->validate($workflow);

	$report = implode("\n", array_map(strval(...), $result->getProblems()));

	Assert::same([], $result->getErrors(), "errors in {$workflow->name}:\n{$report}");
	Assert::same([], $result->getWarnings(), "warnings in {$workflow->name}:\n{$report}");
}

// concrete expectations, so the test doesn't go blind if the files ever emptied out
$cardDev = $parser->parseFile($root . '/workflows/card-dev.json');
Assert::same('card-dev', $cardDev->name);
Assert::count(27, $cardDev->steps);
Assert::true(isset($cardDev->inputs['shortId']));
Assert::false($cardDev->inputs['curlrc']->required);

$sync = $parser->parseFile($root . '/workflows/sync.json');
Assert::same('sync', $sync->name);
Assert::count(7, $sync->steps);

// jptq-task assembles `donut <workflow> --flag=…` into a text literal, out of
// reach of static validation (which only knows {%…%} inside args). The flag's
// name must be an input name that card-dev and card-spec actually declare —
// otherwise the queue fills with tasks that fail with code 2 when consumed.
$cardSpec = $parser->parseFile($root . '/workflows/card-spec.json');
$jptqTask = $repo->get('jptq-task');
$flagsChecked = 0;
$flagNames = [];

foreach ($jptqTask->args as $group) {
	foreach ($group as $template) {
		if (\preg_match('~^--([A-Za-z0-9_]+)=~', $template->getSource(), $m) === 1) {
			$flag = $m[1];
			Assert::true(isset($cardDev->inputs[$flag]), "card-dev declares input \"{$flag}\"");
			Assert::true(isset($cardSpec->inputs[$flag]), "card-spec declares input \"{$flag}\"");
			$flagNames[] = $flag;
			$flagsChecked++;
		}
	}
}

Assert::same(8, $flagsChecked);

// the opposite direction: every required input of card-dev and card-spec must
// actually be supplied by jptq-task as a flag — otherwise the queue fills with
// tasks that fail with code 2 when consumed, because the workflow doesn't get what it needs
foreach (['card-dev' => $cardDev, 'card-spec' => $cardSpec] as $workflowName => $workflowToCheck) {
	foreach ($workflowToCheck->inputs as $inputName => $input) {
		if ($input->required) {
			Assert::true(
				\in_array($inputName, $flagNames, true),
				"{$workflowName}: required input \"{$inputName}\" is missing among the flags jptq-task assembles"
			);
		}
	}
}

// the "workflow" literal in every jptq-task step inside sync must point to a
// file that actually exists — otherwise the queue enqueues a task for a workflow
// the consumer won't find
$collectJptqWorkflowNames = function (array $steps) use (&$collectJptqWorkflowNames): array {
	$names = [];

	foreach ($steps as $step) {
		if ($step instanceof Donut\Format\RunStep && $step->block === 'jptq-task' && isset($step->in['workflow'])) {
			$names[] = $step->in['workflow']->getSource();

		} elseif ($step instanceof Donut\Format\IfStep) {
			$names = [...$names, ...$collectJptqWorkflowNames($step->then), ...$collectJptqWorkflowNames($step->else)];

		} elseif ($step instanceof Donut\Format\ForeachStep) {
			$names = [...$names, ...$collectJptqWorkflowNames($step->steps)];
		}
	}

	return $names;
};

$jptqWorkflowNames = $collectJptqWorkflowNames($sync->steps);

Assert::notSame([], $jptqWorkflowNames, 'sync actually enqueues tasks via jptq-task');

foreach (\array_unique($jptqWorkflowNames) as $workflowName) {
	Assert::true(
		\is_file($root . '/workflows/' . $workflowName . '.json'),
		"workflows/{$workflowName}.json exists"
	);
}

// repo-check is in the rewrite because of the else branch: message is set in both then and else
// and is read after the if. Without both branches it would be a key written in only one branch,
// i.e. a warning — and the zero-warnings assertion above would fail. If someone
// removed that other branch, this should fail, not something distant.
$repoCheck = $parser->parseFile($root . '/workflows/repo-check.json');
$branching = $repoCheck->steps[1];
Assert::type(Donut\Format\IfStep::class, $branching);
Assert::count(1, $branching->then);
Assert::count(1, $branching->else);
Assert::same('message', $branching->then[0]->key);
Assert::same('message', $branching->else[0]->key);
