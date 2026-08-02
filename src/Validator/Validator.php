<?php

declare(strict_types=1);

namespace Donut\Validator;

use Donut\BlockRepository;
use Donut\Format\Block;
use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * Statická validace workflow podle sekce 5 specifikace.
 *
 * Běží před spuštěním prvního kroku. Kontroluje zapojení kroků na kameny
 * a v Tasku 7 i tok klíčů mapou.
 */
final class Validator
{
	public function __construct(
		private readonly BlockRepository $blocks,
	) {
	}


	public function validate(Workflow $workflow): Result
	{
		$result = new Result;
		$this->checkSteps($workflow->steps, $workflow->name . '.json:steps', $result);

		return $result;
	}


	/**
	 * @param array<int, Step> $steps
	 */
	private function checkSteps(array $steps, string $path, Result $result): void
	{
		foreach ($steps as $i => $step) {
			$at = "{$path}[{$i}]";

			if ($step instanceof RunStep) {
				$this->checkRun($step, $at, $result);

			} elseif ($step instanceof IfStep) {
				$this->checkCondition($step->condition, $at, $result);
				$this->checkSteps($step->then, "{$at}.then", $result);
				$this->checkSteps($step->else, "{$at}.else", $result);

			} elseif ($step instanceof SetStep) {
				$this->checkKeyName($step->key, $at, $result);

			} elseif ($step instanceof ForeachStep) {
				$this->checkKeyName($step->as, $at, $result);
				$this->checkSteps($step->steps, "{$at}.steps", $result);
			}
		}
	}


	private function checkRun(RunStep $step, string $at, Result $result): void
	{
		if (!$this->blocks->has($step->block)) {
			$result->add(Problem::error($at, "kámen \"{$step->block}\" neexistuje"));
			return;
		}

		$block = $this->blocks->get($step->block);

		foreach ($step->in as $name => $template) {
			if ($name === 'STDIN') {
				if ($block->stdin === null) {
					$result->add(Problem::error(
						$at,
						"kámen \"{$block->name}\" nečte stdin, ale krok ho plní"
					));
				}

			} elseif (!isset($block->inputs[$name])) {
				$result->add(Problem::error(
					$at,
					"kámen \"{$block->name}\" nedeklaruje vstup \"{$name}\""
				));
			}
		}

		foreach ($block->inputs as $name => $input) {
			if ($input->required && !isset($step->in[$name]) && $input->default === null) {
				$result->add(Problem::error(
					$at,
					"povinný vstup \"{$name}\" kamene \"{$block->name}\" není naplněn"
				));
			}
		}

		if ($block->stdin !== null && $block->stdin->required && !isset($step->in['STDIN'])) {
			$result->add(Problem::error(
				$at,
				"kámen \"{$block->name}\" vyžaduje stdin, krok ho neplní"
			));
		}

		$this->checkStdinNotInArgs($block, $at, $result);

		foreach ($step->out as $channel => $key) {
			if (!\in_array($channel, RunStep::Channels, true)) {
				$result->add(Problem::error($at, "neznámý kanál \"{$channel}\""));
			}

			$this->checkKeyName($key, $at, $result);
		}
	}


	private function checkStdinNotInArgs(Block $block, string $at, Result $result): void
	{
		foreach ($block->args as $group) {
			foreach ($group as $template) {
				if (\in_array('STDIN', $template->getKeys(), true)) {
					$result->add(Problem::error(
						$at,
						"{%STDIN%} použito v args kamene \"{$block->name}\""
					));

					return;
				}
			}
		}
	}


	private function checkCondition(Condition $condition, string $at, Result $result): void
	{
		if (!\in_array($condition->op, Condition::Operators, true)) {
			$result->add(Problem::error($at, "neznámý operátor \"{$condition->op}\""));
		}
	}


	private function checkKeyName(string $key, string $at, Result $result): void
	{
		if (!Template::isKeyName($key)) {
			$result->add(Problem::error(
				$at,
				"klíč \"{$key}\" není platné jméno"
			));
		}
	}
}
