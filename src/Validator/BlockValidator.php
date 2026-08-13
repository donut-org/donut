<?php

declare(strict_types=1);

namespace Donut\Validator;

use Donut\Format\Block;


/**
 * Kontroly, které se dívají jen na kámen samotný.
 *
 * Bydlí zvlášť, protože Validator drží BlockRepository kvůli dohledávání
 * kamenů podle jména — kontrola samotného kamene nepotřebuje nic než ten
 * kámen. Validator ji volá u kroku run, GUI při ukládání.
 */
final class BlockValidator
{
	/**
	 * @param  string|null $location kde se problém hlásí; výchozí je soubor
	 *                               kamene, Validator předává cestu ke kroku
	 */
	public function validate(Block $block, ?string $location = null): Result
	{
		$at = $location ?? $block->name . '.json';
		$result = new Result;

		$this->checkStdinNotInArgs($block, $at, $result);
		$this->checkArgsInputsDeclared($block, $at, $result);

		return $result;
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


	/**
	 * Klíč v args, který kámen nedeklaruje jako vstup, není chybějící hodnota
	 * — je to překlep, který CommandLine nemůže odlišit od legitimně
	 * nevyplněného vstupu a tiše by mu vypadla celá skupina argumentů.
	 * {%STDIN%} v args řeší checkStdinNotInArgs() vlastní hláškou.
	 */
	private function checkArgsInputsDeclared(Block $block, string $at, Result $result): void
	{
		$reported = [];

		foreach ($block->args as $group) {
			foreach ($group as $template) {
				foreach ($template->getKeys() as $key) {
					if ($key === 'STDIN' || isset($block->inputs[$key]) || isset($reported[$key])) {
						continue;
					}

					$reported[$key] = true;
					$result->add(Problem::error(
						$at,
						"kámen \"{$block->name}\" používá v args proměnnou \"{$key}\", kterou nedeklaruje"
					));
				}
			}
		}
	}
}
