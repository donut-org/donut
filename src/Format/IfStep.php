<?php

declare(strict_types=1);

namespace Donut\Format;


final class IfStep implements Step
{
	/**
	 * @param array<int, Step> $then
	 * @param array<int, Step> $else
	 */
	public function __construct(
		public readonly Condition $condition,
		public readonly array $then = [],
		public readonly array $else = [],
		public readonly ?string $name = null,
	) {
	}
}
