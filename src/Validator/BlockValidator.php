<?php

declare(strict_types=1);

namespace Donut\Validator;

use Donut\Format\Block;


/**
 * Checks that look only at the block itself.
 *
 * Lives separately because Validator holds a BlockRepository for looking up
 * blocks by name — checking the block itself needs nothing but the block.
 * Validator calls it for a run step, GUI calls it on save.
 */
final class BlockValidator
{
	/**
	 * @param  string|null $location where to report the problem; default is
	 *                               the block's file, Validator passes the step's path
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
						"{%STDIN%} used in args of block \"{$block->name}\""
					));

					return;
				}
			}
		}
	}


	/**
	 * A key in args that the block does not declare as an input is not a
	 * missing value — it is a typo, which CommandLine cannot tell apart from
	 * a legitimately unfilled input, and it would silently drop the whole
	 * group of arguments. {%STDIN%} in args is handled by
	 * checkStdinNotInArgs() with its own message.
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
						"block \"{$block->name}\" uses variable \"{$key}\" in args without declaring it"
					));
				}
			}
		}
	}
}
