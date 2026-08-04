<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class RunStep implements Step
{
	/** Kanály, které smí stát v out. */
	public const Channels = ['result', 'stderr', 'exit_code'];

	/**
	 * @param array<string, Template> $in  vstup kamene => šablona; klíč STDIN plní standardní vstup
	 * @param array<string, string>   $out kanál => klíč v mapě enginu
	 * @param bool|array<int, int>|null $allowFailure null = převzít z kamene
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
