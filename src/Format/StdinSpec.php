<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Přítomnost tohoto objektu znamená, že kámen čte standardní vstup.
 */
final class StdinSpec
{
	public function __construct(
		public readonly bool $required = true,
		public readonly ?string $description = null,
	) {
	}
}
