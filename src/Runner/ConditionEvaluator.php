<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Format\Condition;


/**
 * Vyhodnocení podmínky kroku `if`. Žádný výraz, jen {left, op, right}.
 */
final class ConditionEvaluator
{
	/**
	 * @param  array<string, string> $map
	 * @throws RunFailedException
	 * @throws \Donut\MissingKeyException
	 */
	public static function evaluate(Condition $condition, array $map, string $location): bool
	{
		$op = $condition->op;
		$left = $condition->left->render($map);

		if (\in_array($op, Condition::UnaryOperators, true)) {
			return match ($op) {
				'empty' => $left === '',
				'not_empty' => $left !== '',
			};
		}

		if (!\in_array($op, Condition::Operators, true)) {
			throw new RunFailedException("{$location}: neznámý operátor \"{$op}\".");
		}

		if ($condition->right === null) {
			throw new RunFailedException("{$location}: operátor \"{$op}\" vyžaduje 'right'.");
		}

		$right = $condition->right->render($map);

		return match ($op) {
			'eq' => $left === $right,
			'neq' => $left !== $right,
			'contains' => \str_contains($left, $right),
			default => self::compare($op, $left, $right, $location),
		};
	}


	/**
	 * @throws RunFailedException
	 */
	private static function compare(string $op, string $left, string $right, string $location): bool
	{
		if (!\is_numeric($left) || !\is_numeric($right)) {
			throw new RunFailedException(
				"{$location}: operátor \"{$op}\" potřebuje čísla, dostal \"{$left}\" a \"{$right}\"."
			);
		}

		$a = (float) $left;
		$b = (float) $right;

		return match ($op) {
			'gt' => $a > $b,
			'gte' => $a >= $b,
			'lt' => $a < $b,
			'lte' => $a <= $b,
			default => throw new RunFailedException("{$location}: neznámý operátor \"{$op}\"."),
		};
	}
}
