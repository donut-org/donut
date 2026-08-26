<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


/**
 * A parametrized function over a single command. Knows nothing about the
 * workflow that calls it, nor about keys in the engine map.
 */
final class Block
{
	/**
	 * @param array<int, array<int, Template>> $args   argument groups
	 * @param array<string, Input>             $inputs keyed by input name
	 * @param bool|array<int, int>             $allowFailure
	 *        false = only 0, true = anything, array = list of allowed exit codes
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $command,
		public readonly array $args,
		public readonly array $inputs = [],
		public readonly ?StdinSpec $stdin = null,
		public readonly ?int $timeout = null,
		public readonly bool|array $allowFailure = false,
		public readonly ?string $description = null,
	) {
	}
}
