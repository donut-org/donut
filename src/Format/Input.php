<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Deklarace jedné proměnné dosazované do args kamene.
 */
final class Input
{
	public function __construct(
		public readonly string $name,
		public readonly bool $required = true,
		public readonly ?string $default = null,
		public readonly ?string $description = null,
	) {
	}
}
