<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


/**
 * Parametrizovaná funkce nad jedním příkazem. Neví nic o workflow, které ji
 * volá, ani o klíčích v mapě enginu.
 */
final class Block
{
	/**
	 * @param array<int, array<int, Template>> $args   skupiny argumentů
	 * @param array<string, Input>             $inputs klíčem je jméno vstupu
	 * @param bool|array<int, int>             $allowFailure
	 *        false = jen 0, true = cokoliv, pole = výčet povolených exit kódů
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
