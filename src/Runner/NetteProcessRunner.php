<?php

declare(strict_types=1);

namespace Donut\Runner;

use Nette\Utils\Process;


/**
 * Spouští příkazy přes Nette\Utils\Process::runExecutable().
 *
 * Nikdy runCommand() — ten by řetězec interpretoval shellem.
 *
 * Prostředí i pracovní adresář dědí potomek od runneru ($env a $directory
 * zůstávají null). Pravidlo „nic z prostředí neprochází" ze specifikace se
 * týká mapy enginu, ne spouštěných programů: gh potřebuje $HOME a svůj
 * token, git $PATH a SSH_AUTH_SOCK.
 */
final class NetteProcessRunner implements ProcessRunner
{
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult
	{
		$process = Process::runExecutable(
			executable: $command,
			arguments: \array_values($args),
			stdin: $stdin,
			stdout: null,
			stderr: $captureStderr ? null : STDERR,
			timeout: $timeout === null ? null : (float) $timeout,
		);

		$exitCode = $process->getExitCode();

		return new ProcessResult(
			stdout: self::trimTrailingNewlines($process->getStdOutput()),
			stderr: $captureStderr ? self::trimTrailingNewlines($process->getStdError()) : null,
			exitCode: $exitCode,
		);
	}


	/**
	 * Odřezává koncové odřádkování stejně jako $(...) v shellu.
	 *
	 * Bez toho by `jq -r '.id'` vrátil "5f2abc\n" a ta hodnota by se pak
	 * vlepila doprostřed URL. Vnitřní odřádkování zůstává nedotčené.
	 */
	private static function trimTrailingNewlines(string $output): string
	{
		return \rtrim($output, "\r\n");
	}
}
