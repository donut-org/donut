<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Runs one command.
 *
 * The interface exists for the Runner's testability, not for interchangeability
 * — there is only one production implementation.
 */
interface ProcessRunner
{
	/**
	 * @param  list<string> $args
	 * @param  bool $captureStdout true = into memory, false = stream to the terminal
	 * @param  bool $captureStderr true = into memory, false = stream to the terminal
	 * @param  ?int $timeout seconds; null disables the limit
	 * @throws \Nette\Utils\ProcessTimeoutException
	 * @throws \Nette\Utils\ProcessFailedException the process could not be started at all
	 */
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStdout,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult;
}
