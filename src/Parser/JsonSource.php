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

			$inputs[$name] = new Input(
				name: $name,
				required: isset($spec['required']) ? (bool) $spec['required'] : true,
				default: isset($spec['default']) && \is_scalar($spec['default']) ? (string) $spec['default'] : null,
				description: isset($spec['description']) && \is_scalar($spec['description']) ? (string) $spec['description'] : null,
			);
		}

		return $inputs;
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
