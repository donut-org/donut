<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class ForeachStep implements Step
{
	/** @param array<int, Step> $steps */
	public function __construct(
		public readonly Template $over,
		public readonly string $as,
		public readonly array $steps = [],
		public readonly ?string $name = null,
	) {
	}


	public function getName(): ?string
	{
		return $this->name;
	}
}
