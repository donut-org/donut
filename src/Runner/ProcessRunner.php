<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Spuštění jednoho příkazu.
 *
 * Rozhraní existuje kvůli testovatelnosti Runneru, ne kvůli zaměnitelnosti —
 * produkční implementace je jen jedna.
 */
interface ProcessRunner
{
	/**
	 * @param  list<string> $args
	 * @param  bool $captureStderr true = do paměti, false = streamovat na terminál
	 * @param  ?int $timeout sekundy; null vypíná limit
	 * @throws \Nette\Utils\ProcessTimeoutException
	 */
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult;
}
