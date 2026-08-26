<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Result of one process run.
 *
 * $stdout and $stderr are both null when the given output was streamed to
 * the terminal instead of being captured into memory.
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
