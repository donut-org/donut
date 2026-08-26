<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * The presence of this object means the block reads standard input.
 */
final class StdinSpec
{
	public function __construct(
		public readonly bool $required = true,
		public readonly ?string $description = null,
	) {
	}
}
