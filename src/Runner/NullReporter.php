<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Stays silent. For tests and for callers that don't care about progress.
 */
final class NullReporter implements Reporter
{
	public function step(string $path, string $label): void
	{
	}


	public function warning(string $message): void
	{
	}
}
