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
	/** @var array<int, string> */
	private array $writtenAnywhere = [];


	public function __construct(
		private readonly BlockRepository $blocks,
	) {
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
					"klíč \"{$key}\" se zapisuje a nikdy nečte"
				));
			}
		}

		foreach (\array_keys($workflow->inputs) as $key) {
			if (!\in_array($key, $read, true)) {
				$result->add(Problem::warning(
					$location,
					"vstup \"{$key}\" se nikde nepoužívá"
				));
			}
		}

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
				$this->readStrict($step->condition->left, $at, 'podmínka', $result, $flow);

				if ($step->condition->right !== null) {
					$this->readStrict($step->condition->right, $at, 'podmínka', $result, $flow);
				}

				$then = $flow->branch();
				$this->checkSteps($step->then, "{$at}.then", $result, $then);
				$flow->mergeAsMaybe($then);

				$else = $flow->branch();
				$this->checkSteps($step->else, "{$at}.else", $result, $else);
				$flow->mergeAsMaybe($else);

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
			$this->readTolerant($template, $at, 'šablona', $result, $flow);
		}

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
			$flow->write($key);
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


	/**
	 * Čtení, které snese klíč zapsaný jen v jedné větvi — jen varuje.
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
					"{$what} čte klíč \"{$key}\", který nemusí existovat"
				));

			} else {
				$result->add(Problem::error($at, $this->missingKeyMessage($what, $key)));
			}
		}
	}


	/**
	 * Čtení v podmínce a ve foreach.over. Z těch se nedá vycouvat, takže
	 * „možná" nestačí.
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

			if (!$flow->isKnown($key)) {
				$result->add(Problem::error($at, $this->missingKeyMessage($what, $key)));
			}
		}
	}


	/**
	 * Klíč, který nikdo nikdy nezapisuje, je překlep; klíč zapsaný později
	 * je chyba pořadí. Hlášky se liší, aby se to dalo rozlišit.
	 */
	private function missingKeyMessage(string $what, string $key): string
	{
		return \in_array($key, $this->writtenAnywhere, true)
			? "{$what} čte klíč \"{$key}\", který v tomto místě nemohl vzniknout"
			: "{$what} čte klíč \"{$key}\", který žádný krok nezapisuje";
	}


	/**
	 * Všechny klíče, které kterýkoliv krok kdekoliv zapisuje — bez ohledu
	 * na pořadí a větvení. Slouží k rozlišení překlepu od špatného pořadí.
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
