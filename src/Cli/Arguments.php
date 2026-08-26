<?php

declare(strict_types=1);

namespace Donut\Cli;


/**
 * Command-line breakdown.
 *
 * Only the --key=value form is supported. The two-word form is not
 * specified, and unambiguity is worth more here than convenience.
 */
final class Arguments
{
	/** @param array<string, string> $values */
	private function __construct(
		public readonly ?string $workflow,
		public readonly array $values,
		public readonly bool $help,
		public readonly bool $list,
	) {
	}


	/**
	 * @param  array<int, string> $argv the first element is the program name
	 * @throws UsageException
	 */
	public static function parse(array $argv): self
	{
		$workflow = null;
		$values = [];
		$help = false;
		$list = false;

		foreach (\array_slice($argv, 1) as $arg) {
			if ($arg === '--help') {
				$help = true;

			} elseif ($arg === '--list') {
				$list = true;

			} elseif (\str_starts_with($arg, '--')) {
				$name = \substr($arg, 2);

				// covers both bare "--" and "--=value"; both are a key without a name
				if ($name === '' || \str_starts_with($name, '=')) {
					throw new UsageException("Argument \"{$arg}\" has no key name.");
				}

				$position = \strpos($name, '=');

				if ($position === false) {
					throw new UsageException("Argument --{$name} must have the form --{$name}=value.");
				}

				$key = \substr($name, 0, $position);

				if ($key === 'help' || $key === 'list') {
					throw new UsageException("Argument --{$key} is a flag, it takes no value.");
				}

				if (isset($values[$key])) {
					throw new UsageException("Argument --{$key} is given more than once.");
				}

				$values[$key] = \substr($name, $position + 1);

			} elseif (\str_starts_with($arg, '-')) {
				throw new UsageException("Unknown argument \"{$arg}\".");

			} elseif ($workflow !== null) {
				throw new UsageException("Workflow is given more than once: \"{$workflow}\" and \"{$arg}\".");

			} else {
				$workflow = $arg;
			}
		}

		return new self($workflow, $values, $help, $list);
	}
}
