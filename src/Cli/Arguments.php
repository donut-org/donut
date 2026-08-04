<?php

declare(strict_types=1);

namespace Donut\Cli;


/**
 * Rozklad příkazové řádky.
 *
 * Podporuje se jen tvar --klic=hodnota. Tvar se dvěma slovy specifikace
 * neuvádí a jednoznačnost je tu cennější než pohodlí.
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
	 * @param  array<int, string> $argv první prvek je jméno programu
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

				// pokrývá holé "--" i "--=hodnota"; obojí je klíč bez jména
				if ($name === '' || \str_starts_with($name, '=')) {
					throw new UsageException("Argument \"{$arg}\" nemá jméno klíče.");
				}

				$position = \strpos($name, '=');

				if ($position === false) {
					throw new UsageException("Argument --{$name} musí mít tvar --{$name}=hodnota.");
				}

				$key = \substr($name, 0, $position);

				if ($key === 'help' || $key === 'list') {
					throw new UsageException("Argument --{$key} je příznak, nemá hodnotu.");
				}

				if (isset($values[$key])) {
					throw new UsageException("Argument --{$key} je uvedený víckrát.");
				}

				$values[$key] = \substr($name, $position + 1);

			} elseif (\str_starts_with($arg, '-')) {
				throw new UsageException("Neznámý argument \"{$arg}\".");

			} elseif ($workflow !== null) {
				throw new UsageException("Workflow je uvedené víckrát: \"{$workflow}\" a \"{$arg}\".");

			} else {
				$workflow = $arg;
			}
		}

		return new self($workflow, $values, $help, $list);
	}
}
