<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Input;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Části parsování společné kamenům i workflow.
 *
 * Oba formáty se čtou stejně a oba deklarují inputs; kámen a krok navíc
 * sdílejí tvar allow_failure. Bez tohohle místa by se to opisovalo
 * a měnilo dvakrát.
 */
final class JsonSource
{
	/**
	 * @return array<mixed>
	 * @throws ParseException
	 */
	public static function readFile(string $path): array
	{
		$content = @\file_get_contents($path);

		if ($content === false) {
			throw new ParseException("Soubor '{$path}' nejde přečíst.");
		}

		try {
			$data = Json::decode($content, forceArrays: true);

		} catch (JsonException $e) {
			throw new ParseException("Soubor '{$path}' není platný JSON: {$e->getMessage()}", 0, $e);
		}

		if (!\is_array($data)) {
			throw new ParseException("Soubor '{$path}' musí obsahovat objekt.");
		}

		return $data;
	}


	/**
	 * Odmítne klíče, které nejsou v seznamu známých — pro libovolnou úroveň
	 * vnoření, ne jen kořen souboru. `$what` je jak se v hlášce odkázat na
	 * místo; prázdný řetězec pro kořen souboru.
	 *
	 * @param  array<mixed> $data
	 * @param  array<int, string> $known
	 * @throws ParseException
	 */
	public static function rejectUnknownKeys(array $data, array $known, string $location, string $what): void
	{
		foreach (\array_keys($data) as $key) {
			if (\in_array($key, $known, true)) {
				continue;
			}

			$message = $what === ''
				? "neznámý klíč '{$key}'."
				: "{$what} má neznámý klíč '{$key}'.";

			throw new ParseException("{$location}: {$message}");
		}
	}


	/**
	 * Přečte klíč `inputs`. Chybějící klíč znamená prázdnou deklaraci.
	 *
	 * @param  array<mixed> $data celý objekt kamene nebo workflow
	 * @return array<string, Input>
	 * @throws ParseException
	 */
	public static function parseInputs(array $data, string $location): array
	{
		if (!isset($data['inputs'])) {
			return [];
		}

		if (!\is_array($data['inputs'])) {
			throw new ParseException("{$location}: klíč 'inputs' musí být objekt.");
		}

		$inputs = [];

		foreach ($data['inputs'] as $name => $spec) {
			if (!\is_string($name)) {
				throw new ParseException("{$location}: jména vstupů musí být řetězce.");
			}

			if (!\is_array($spec)) {
				throw new ParseException("{$location}: vstup '{$name}' musí být objekt.");
			}

			self::rejectUnknownKeys($spec, ['required', 'default', 'description'], $location, "vstup '{$name}'");

			$inputs[$name] = new Input(
				name: $name,
				required: isset($spec['required']) ? (bool) $spec['required'] : true,
				default: self::optionalString($spec, 'default', $location, "default vstupu '{$name}'"),
				description: self::optionalString($spec, 'description', $location, "description vstupu '{$name}'"),
			);
		}

		return $inputs;
	}


	/**
	 * Nepovinná textová hodnota. Chybí -> null. Skalár -> text.
	 * Pole nebo objekt -> chyba, protože v mapě jsou jen texty.
	 *
	 * @param  array<mixed> $data
	 * @param  string $what jak se na hodnotu odkázat v hlášce
	 * @throws ParseException
	 */
	public static function optionalString(array $data, string $key, string $location, string $what): ?string
	{
		if (!isset($data[$key])) {
			return null;
		}

		if (!\is_scalar($data[$key])) {
			throw new ParseException("{$location}: {$what} musí být řetězec.");
		}

		return (string) $data[$key];
	}


	/**
	 * @param  string $what jak se na pole odkázat v hlášce (`allow_failure`,
	 *                      nebo `steps[0].allow_failure` u kroku)
	 * @return bool|array<int, int>
	 * @throws ParseException
	 */
	public static function parseAllowFailure(mixed $value, string $location, string $what): bool|array
	{
		if (\is_bool($value)) {
			return $value;
		}

		if (\is_array($value)) {
			$codes = [];

			foreach ($value as $code) {
				if (!\is_int($code)) {
					throw new ParseException(
						"{$location}: {$what} jako pole musí obsahovat jen celá čísla."
					);
				}

				$codes[] = $code;
			}

			return $codes;
		}

		throw new ParseException(
			"{$location}: {$what} musí být true, false, nebo pole celých čísel."
		);
	}
}
