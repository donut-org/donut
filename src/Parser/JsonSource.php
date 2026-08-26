<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Input;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Parsing parts shared by blocks and workflows.
 *
 * Both formats are read the same way and both declare inputs; a block and a step
 * additionally share the allow_failure shape. Without this place it would be
 * duplicated and changed twice.
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
			throw new ParseException("File '{$path}' cannot be read.");
		}

		try {
			$data = Json::decode($content, forceArrays: true);

		} catch (JsonException $e) {
			throw new ParseException("File '{$path}' is not valid JSON: {$e->getMessage()}", 0, $e);
		}

		if (!\is_array($data)) {
			throw new ParseException("File '{$path}' must contain an object.");
		}

		return $data;
	}


	/**
	 * Rejects keys that are not in the list of known ones — for any nesting
	 * level, not just the file root. `$what` is how to refer to the location
	 * in the message; an empty string for the file root.
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
				? "unknown key '{$key}'."
				: "{$what} has an unknown key '{$key}'.";

			throw new ParseException("{$location}: {$message}");
		}
	}


	/**
	 * Reads the `inputs` key. A missing key means an empty declaration.
	 *
	 * @param  array<mixed> $data the whole block or workflow object
	 * @return array<string, Input>
	 * @throws ParseException
	 */
	public static function parseInputs(array $data, string $location): array
	{
		if (!isset($data['inputs'])) {
			return [];
		}

		if (!\is_array($data['inputs'])) {
			throw new ParseException("{$location}: key 'inputs' must be an object.");
		}

		$inputs = [];

		foreach ($data['inputs'] as $name => $spec) {
			if (!\is_string($name)) {
				throw new ParseException("{$location}: input names must be strings.");
			}

			if (!\is_array($spec)) {
				throw new ParseException("{$location}: input '{$name}' must be an object.");
			}

			self::rejectUnknownKeys($spec, ['required', 'default', 'description'], $location, "input '{$name}'");

			$inputs[$name] = new Input(
				name: $name,
				required: isset($spec['required']) ? (bool) $spec['required'] : true,
				default: self::optionalString($spec, 'default', $location, "default of input '{$name}'"),
				description: self::optionalString($spec, 'description', $location, "description of input '{$name}'"),
			);
		}

		return $inputs;
	}


	/**
	 * Optional text value. Missing -> null. Scalar -> text.
	 * Array or object -> error, because the map holds only text.
	 *
	 * @param  array<mixed> $data
	 * @param  string $what how to refer to the value in the message
	 * @throws ParseException
	 */
	public static function optionalString(array $data, string $key, string $location, string $what): ?string
	{
		if (!isset($data[$key])) {
			return null;
		}

		if (!\is_scalar($data[$key])) {
			throw new ParseException("{$location}: {$what} must be a string.");
		}

		return (string) $data[$key];
	}


	/**
	 * @param  string $what how to refer to the field in the message (`allow_failure`,
	 *                      or `steps[0].allow_failure` for a step)
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
						"{$location}: {$what} as an array must contain only integers."
					);
				}

				$codes[] = $code;
			}

			return $codes;
		}

		throw new ParseException(
			"{$location}: {$what} must be true, false, or an array of integers."
		);
	}
}
