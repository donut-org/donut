<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Format\Block;
use Donut\Template;


/**
 * A command and its arguments, assembled from a block and the values a step
 * passed to it.
 *
 * An argument group drops out when one of its variables evaluates to empty.
 * An unfilled input and an input evaluated to an empty string are the same
 * thing — see the Argument groups section of the specification.
 */
final class CommandLine
{
	/** @param list<string> $args */
	private function __construct(
		public readonly string $command,
		public readonly array $args,
	) {
	}


	/**
	 * @param  array<string, Template> $in  block input => template from the step
	 * @param  array<string, string>   $map engine map
	 * @param  string                  $location step path for messages
	 * @throws RunFailedException
	 * @throws \Donut\MissingKeyException
	 */
	public static function build(Block $block, array $in, array $map, string $location): self
	{
		$values = self::resolveValues($block, $in, $map, $location);
		$args = [];

		foreach ($block->args as $group) {
			if (self::groupDropsOut($group, $values)) {
				continue;
			}

			foreach ($group as $template) {
				$args[] = $template->render($values);
			}
		}

		return new self($block->command, $args);
	}


	/**
	 * The input's value: what the step passed, else the block's default, else
	 * unfilled. The resulting value decides, not where it came from.
	 *
	 * @param  array<string, Template> $in
	 * @param  array<string, string>   $map
	 * @return array<string, string>   unfilled inputs are missing from the array
	 * @throws RunFailedException
	 */
	private static function resolveValues(Block $block, array $in, array $map, string $location): array
	{
		$values = [];

		foreach ($block->inputs as $name => $input) {
			if (isset($in[$name])) {
				$value = $in[$name]->render($map);

			} elseif ($input->default !== null) {
				$value = $input->default;

			} else {
				$value = null;
			}

			if ($value === null || $value === '') {
				if ($input->required) {
					throw new RunFailedException(
						"{$location}: required input \"{$name}\" of block \"{$block->name}\" has an empty value."
					);
				}

				continue;
			}

			$values[$name] = $value;
		}

		return $values;
	}


	/**
	 * @param  array<int, Template>  $group
	 * @param  array<string, string> $values
	 */
	private static function groupDropsOut(array $group, array $values): bool
	{
		foreach ($group as $template) {
			foreach ($template->getKeys() as $key) {
				if (!isset($values[$key])) {
					return true;
				}
			}
		}

		return false;
	}
}
