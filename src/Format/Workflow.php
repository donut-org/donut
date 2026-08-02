<?php

declare(strict_types=1);

namespace Donut\Format;


final class Workflow
{
	/**
	 * @param array<string, Input> $inputs
	 * @param array<int, Step>     $steps
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $inputs = [],
		public readonly array $steps = [],
		public readonly ?string $description = null,
	) {
	}
}
