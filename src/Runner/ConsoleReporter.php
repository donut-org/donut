<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Writes progress to STDERR so it doesn't mix with the workflow's output.
 */
final class ConsoleReporter implements Reporter
{
	/** @var resource */
	private $stream;


	/** @param resource|null $stream */
	public function __construct($stream = null)
	{
		$this->stream = $stream ?? STDERR;
	}


	public function step(string $path, string $label): void
	{
		\fwrite($this->stream, "{$path}  {$label}\n");
	}


	public function warning(string $message): void
	{
		\fwrite($this->stream, "warning: {$message}\n");
	}
}
