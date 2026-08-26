<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Reports the progress of a run.
 *
 * The interface exists so the Runner doesn't write to STDERR directly and
 * stays testable. One implementation is for production, the other is empty
 * for tests.
 */
interface Reporter
{
	/**
	 * @param string $path  step path in the form steps[5].then[0]
	 * @param string $label step name, block name, or KEY=value for foreach
	 */
	public function step(string $path, string $label): void;

	public function warning(string $message): void;
}
