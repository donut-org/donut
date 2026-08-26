<?php

declare(strict_types=1);

namespace Donut\Validator;

use Donut\BlockRepository;
use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * Static validation of a workflow per section 5 of the specification.
 *
 * Runs before the first step executes. Checks how steps connect to blocks
 * and, per Task 7, also the flow of keys through the map.
 */
final class Validator
{
	/** @var array<int, string> */
	private array $writtenAnywhere = [];

	private readonly BlockValidator $blockValidator;


	public function __construct(
		private readonly BlockRepository $blocks,
	) {
		$this->blockValidator = new BlockValidator;
	}


	public function validate(Workflow $workflow): Result
	{
		$result = new Result;
		$location = $workflow->name . '.json';

		$this->writtenAnywhere = $this->collectWrittenKeys($workflow->steps);

		$flow = new KeyFlow([...\array_keys($workflow->inputs), 'STDIN', 'CWD']);

		$this->checkSteps($workflow->steps, "{$location}:steps", $result, $flow);

		$read = $flow->getRead();

		foreach ($flow->getWritten() as $key) {
			if (!\in_array($key, $read, true)) {
				$result->add(Problem::warning(
					$location,
					"key \"{$key}\" is written and never read"
				));
			}
		}

		foreach (\array_keys($workflow->inputs) as $key) {
			if (!\in_array($key, $read, true)) {
				$result->add(Problem::warning(
					$location,
					"input \"{$key}\" is never used"
				));
			}
		}

		$result->setKeys($read, $flow->getWritten());

		return $result;
	}


	/**
	 * @param array<int, Step> $steps
	 */
	private function checkSteps(array $steps, string $path, Result $result, KeyFlow $flow): void
	{
		foreach ($steps as $i => $step) {
			$at = "{$path}[{$i}]";

			if ($step instanceof RunStep) {
				$this->checkRun($step, $at, $result, $flow);

			} elseif ($step instanceof IfStep) {
				$this->checkCondition($step->condition, $at, $result);
				$this->readStrict($step->condition->left, $at, 'condition', $result, $flow);

				if ($step->condition->right !== null) {
					$this->readStrict($step->condition->right, $at, 'condition', $result, $flow);
				}

				$then = $flow->branch();
				$else = $flow->branch();

				$this->checkSteps($step->then, "{$at}.then", $result, $then);
				$this->checkSteps($step->else, "{$at}.else", $result, $else);

				$flow->mergeBranches($then, $else);

			} elseif ($step instanceof SetStep) {
				$this->checkKeyName($step->key, $at, $result);
				$this->readTolerant($step->value, $at, 'set', $result, $flow);
				$flow->write($step->key);

			} elseif ($step instanceof ForeachStep) {
				$this->checkKeyName($step->as, $at, $result);
				$this->readStrict($step->over, $at, 'foreach', $result, $flow);

				$body = $flow->branch();
				$body->write($step->as);
				$this->checkSteps($step->steps, "{$at}.steps", $result, $body);
				$flow->mergeAsMaybe($body);
				$flow->writeMaybe($step->as);
			}
		}
	}


	private function checkRun(RunStep $step, string $at, Result $result, KeyFlow $flow): void
	{
		foreach ($step->in as $template) {
			$this->readTolerant($template, $at, 'template', $result, $flow);
		}

		if (!$this->blocks->has($step->block)) {
			$result->add(Problem::error($at, "block \"{$step->block}\" does not exist"));
			return;
		}

		$block = $this->blocks->get($step->block);

		if (isset($block->inputs['stdin'])) {
			$result->add(Problem::error(
				$at,
				"block \"{$block->name}\" must not have an input named \"stdin\" — that is a channel name"
			));
		}

		foreach ($step->in as $name => $template) {
			if ($name === 'stdin') {
				if ($block->stdin === null) {
					$result->add(Problem::error(
						$at,
						"block \"{$block->name}\" does not read stdin, but the step fills it"
					));
				}

			} elseif (!isset($block->inputs[$name])) {
				$result->add(Problem::error(
					$at,
					"block \"{$block->name}\" does not declare input \"{$name}\""
				));
			}
		}

		foreach ($block->inputs as $name => $input) {
			if ($input->required && !isset($step->in[$name]) && $input->default === null) {
				$result->add(Problem::error(
					$at,
					"required input \"{$name}\" of block \"{$block->name}\" is not filled"
				));
			}
		}

		if ($block->stdin !== null && $block->stdin->required && !isset($step->in['stdin'])) {
			$result->add(Problem::error(
				$at,
				"block \"{$block->name}\" requires stdin, the step does not fill it"
			));
		}

		foreach ($this->blockValidator->validate($block, $at)->getProblems() as $problem) {
			$result->add($problem);
		}

		foreach ($step->out as $channel => $key) {
			if (!\in_array($channel, RunStep::Channels, true)) {
				$result->add(Problem::error($at, "unknown channel \"{$channel}\""));
			}

			$this->checkKeyName($key, $at, $result);
			$flow->write($key);
		}
	}


	private function checkCondition(Condition $condition, string $at, Result $result): void
	{
		if (!\in_array($condition->op, Condition::Operators, true)) {
			$result->add(Problem::error($at, "unknown operator \"{$condition->op}\""));
		}

		if (
			\in_array($condition->op, Condition::Operators, true)
			&& !\in_array($condition->op, Condition::UnaryOperators, true)
			&& $condition->right === null
		) {
			$result->add(Problem::error($at, "operator \"{$condition->op}\" requires 'right'"));
		}
	}


	private function checkKeyName(string $key, string $at, Result $result): void
	{
		if (!Template::isKeyName($key)) {
			$result->add(Problem::error(
				$at,
				"key \"{$key}\" is not a valid name"
			));
		}
	}


	/**
	 * A read that tolerates a key written in only one branch — just warns.
	 */
	private function readTolerant(
		Template $template,
		string $at,
		string $what,
		Result $result,
		KeyFlow $flow,
	): void
	{
		foreach ($template->getKeys() as $key) {
			$flow->markRead($key);

			if ($flow->isKnown($key)) {
				continue;
			}

			if ($flow->isMaybe($key)) {
				$result->add(Problem::warning(
					$at,
					"{$what} reads key \"{$key}\", which may not exist"
				));

			} else {
				$result->add(Problem::error($at, $this->missingKeyMessage($what, $key)));
			}
		}
	}


	/**
	 * A read in a condition and in foreach.over. There is no backing out of
	 * those, so "maybe" is not enough.
	 */
	private function readStrict(
		Template $template,
		string $at,
		string $what,
		Result $result,
		KeyFlow $flow,
	): void
	{
		foreach ($template->getKeys() as $key) {
			$flow->markRead($key);

			if ($flow->isKnown($key)) {
				continue;
			}

			$message = $flow->isMaybe($key)
				? "{$what} reads key \"{$key}\", which is created only on some paths — it must not decide which steps run"
				: $this->missingKeyMessage($what, $key);

			$result->add(Problem::error($at, $message));
		}
	}


	/**
	 * A key that no step ever writes is a typo; a key written later is an
	 * ordering error. The messages differ so the two can be told apart.
	 */
	private function missingKeyMessage(string $what, string $key): string
	{
		return \in_array($key, $this->writtenAnywhere, true)
			? "{$what} reads key \"{$key}\", which cannot have been created at this point"
			: "{$what} reads key \"{$key}\", which no step writes";
	}


	/**
	 * All keys that any step anywhere writes — regardless of order and
	 * branching. Used to tell a typo apart from a wrong order.
	 *
	 * @param  array<int, Step> $steps
	 * @return array<int, string>
	 */
	private function collectWrittenKeys(array $steps): array
	{
		$keys = [];

		foreach ($steps as $step) {
			if ($step instanceof RunStep) {
				foreach ($step->out as $key) {
					$keys[] = $key;
				}

			} elseif ($step instanceof SetStep) {
				$keys[] = $step->key;

			} elseif ($step instanceof IfStep) {
				$keys = [
					...$keys,
					...$this->collectWrittenKeys($step->then),
					...$this->collectWrittenKeys($step->else),
				];

			} elseif ($step instanceof ForeachStep) {
				$keys[] = $step->as;
				$keys = [...$keys, ...$this->collectWrittenKeys($step->steps)];
			}
		}

		return \array_values(\array_unique($keys));
	}
}
