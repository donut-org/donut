<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Výsledek jednoho spuštěného procesu.
 *
 * $stdout i $stderr jsou null, když se daný výstup streamoval na terminál
 * místo zachytávání do paměti.
 */
final class ProcessResult
{
	public function __construct(
		public readonly ?string $stdout,
		public readonly ?string $stderr,
		public readonly int $exitCode,
	) {
	}
}
