<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class SetStep implements Step
{
	public function __construct(
		public readonly string $key,
		public readonly Template $value,
		public readonly ?string $name = null,
	) {
	}
}
