<?php

declare(strict_types=1);

namespace Donut;


/**
 * Kde leží kameny a workflow: `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`.
 *
 * Je to hodnota, ne přístup na disk — existenci adresářů neověřuje. Chybějící
 * adresář hlásí až repozitáře při čtení, aby hláška uměla říct, co se hledalo.
 */
final class Profile
{
	public function __construct(
		private readonly string $name,
		private readonly string $dir,
	) {
	}


	/**
	 * Prostředí bere jako pole, ne přes getenv() uvnitř — jinak by se celá
	 * tabulka okrajových případů nedala otestovat bez putenv().
	 *
	 * @param  array<string, string> $env
	 * @throws Exception prostředí, ze kterého se cesta nedá složit
	 */
	public static function fromEnvironment(array $env): self
	{
		$name = self::value($env, 'DONUT_PROFILE') ?? 'default';

		// Jméno profilu je jméno adresáře. Cesta v něm by znamenala druhý
		// způsob, jak říct „hledej jinde" — od toho je symlink a DONUT_HOME.
		if (\str_contains($name, '/') || \str_contains($name, '\\') || $name === '.' || $name === '..') {
			throw new Exception(
				"DONUT_PROFILE=\"{$name}\": jméno profilu je jméno adresáře, ne cesta."
				. ' Na sadu jinde v souborovém systému udělej symlink.'
			);
		}

		$root = self::value($env, 'DONUT_HOME') ?? self::defaultRoot($env);

		return new self($name, $root . '/' . $name);
	}


	public function name(): string
	{
		return $this->name;
	}


	public function dir(): string
	{
		return $this->dir;
	}


	public function blocksDir(): string
	{
		return $this->dir . '/blocks';
	}


	public function workflowsDir(): string
	{
		return $this->dir . '/workflows';
	}


	/**
	 * @param  array<string, string> $env
	 * @throws Exception
	 */
	private static function defaultRoot(array $env): string
	{
		$config = self::value($env, 'XDG_CONFIG_HOME');

		if ($config !== null) {
			return $config . '/donut';
		}

		$home = self::value($env, 'HOME');

		if ($home === null) {
			throw new Exception(
				'Nevím, kde hledat profily: prostředí nemá HOME ani XDG_CONFIG_HOME.'
				. ' Nastav DONUT_HOME na kořen profilů.'
			);
		}

		return $home . '/.config/donut';
	}


	/**
	 * Prázdná hodnota je totéž co nenastavená — stejné pravidlo, jaké má
	 * formát u nevyplněných vstupů.
	 *
	 * @param array<string, string> $env
	 */
	private static function value(array $env, string $key): ?string
	{
		$value = $env[$key] ?? '';

		return $value === '' ? null : $value;
	}
}
