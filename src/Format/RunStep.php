<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class RunStep implements Step
{
	/** Channels that may appear in out. */
	public const Channels = ['result', 'stderr', 'exit_code'];

	/**
	 * @param array<string, Template> $in  block input => template; the stdin key feeds standard input
	 * @param array<string, string>   $out channel => key in the engine map
	 * @param bool|array<int, int>|null $allowFailure null = inherit from the block
	 */
	public function __construct(
		public readonly string $block,
		public readonly array $in = [],
		public readonly array $out = [],
		public readonly ?int $timeout = null,
		public readonly bool|array|null $allowFailure = null,
		public readonly ?string $name = null,
	) {
	}
}
