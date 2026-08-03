<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Format\Block;
use Donut\Template;


/**
 * Příkaz a jeho argumenty, poskládané z kamene a z hodnot, které mu krok předal.
 *
 * Skupina argumentů vypadne, když se v ní některá proměnná vyhodnotí na
 * prázdno. Nevyplněný vstup a vstup vyhodnocený na prázdný řetězec jsou
 * totéž — viz sekce Skupiny argumentů ve specifikaci.
 */
final class CommandLine
{
	/** @param list<string> $args */
	private function __construct(
		public readonly string $command,
		public readonly array $args,
	) {
	}


	/**
	 * @param  array<string, Template> $in  vstup kamene => šablona ze kroku
	 * @param  array<string, string>   $map mapa enginu
	 * @param  string                  $location cesta ke kroku pro hlášky
	 * @throws RunFailedException
	 * @throws \Donut\MissingKeyException
	 */
	public static function build(Block $block, array $in, array $map, string $location): self
	{
		$values = self::resolveValues($block, $in, $map, $location);
		$args = [];

		foreach ($block->args as $group) {
			if (self::groupDropsOut($group, $values)) {
				continue;
			}

			foreach ($group as $template) {
				$args[] = $template->render($values);
			}
		}

		return new self($block->command, $args);
	}


	/**
	 * Hodnota vstupu: co předal krok, jinak default kamene, jinak nevyplněno.
	 * Rozhoduje výsledná hodnota, ne to, odkud přišla.
	 *
	 * @param  array<string, Template> $in
	 * @param  array<string, string>   $map
	 * @return array<string, string>   nevyplněné vstupy v poli chybí
	 * @throws RunFailedException
	 */
	private static function resolveValues(Block $block, array $in, array $map, string $location): array
	{
		$values = [];

		foreach ($block->inputs as $name => $input) {
			if (isset($in[$name])) {
				$value = $in[$name]->render($map);

			} elseif ($input->default !== null) {
				$value = $input->default;

			} else {
				$value = null;
			}

			if ($value === null || $value === '') {
				if ($input->required) {
					throw new RunFailedException(
						"{$location}: povinný vstup \"{$name}\" kamene \"{$block->name}\" má prázdnou hodnotu."
					);
				}

				continue;
			}

			$values[$name] = $value;
		}

		return $values;
	}


	/**
	 * @param  array<int, Template>  $group
	 * @param  array<string, string> $values
	 */
	private static function groupDropsOut(array $group, array $values): bool
	{
		foreach ($group as $template) {
			foreach ($template->getKeys() as $key) {
				if (!isset($values[$key])) {
					return true;
				}
			}
		}

		return false;
	}
}
