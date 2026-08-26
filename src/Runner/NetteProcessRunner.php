<?php

declare(strict_types=1);

namespace Donut\Runner;

use Nette\Utils\Process;


/**
 * Runs commands via Nette\Utils\Process::runExecutable().
 *
 * Never runCommand() — that would interpret the string through the shell.
 *
 * The child process inherits both the environment and the working directory
 * from the runner ($env and $directory stay null). The specification's rule
 * "nothing from the environment passes through" concerns the engine map, not
 * the spawned programs: gh needs $HOME and its token, git needs $PATH and
 * SSH_AUTH_SOCK.
 */
final class NetteProcessRunner implements ProcessRunner
{
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStdout,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult
	{
		$process = Process::runExecutable(
			executable: $command,
			arguments: $args,
			stdin: $stdin,
			stdout: $captureStdout ? null : STDOUT,
			stderr: $captureStderr ? null : STDERR,
			timeout: $timeout === null ? null : (float) $timeout,
		);

		$exitCode = $process->getExitCode();

		return new ProcessResult(
			stdout: $captureStdout ? self::trimTrailingNewlines($process->getStdOutput()) : null,
			stderr: $captureStderr ? self::trimTrailingNewlines($process->getStdError()) : null,
			exitCode: $exitCode,
		);
	}


	/**
	 * Strips trailing newlines the same way $(...) does in the shell.
	 *
	 * Without this, `jq -r '.id'` would return "5f2abc\n" and that value
	 * would then get pasted into the middle of a URL. Internal newlines
	 * stay untouched.
	 */
	private static function trimTrailingNewlines(string $output): string
	{
		return \rtrim($output, "\r\n");
	}
}
