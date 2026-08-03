<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Mlčí. Pro testy a pro volající, které průběh nezajímá.
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
