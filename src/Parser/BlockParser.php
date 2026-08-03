<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Block;
use Donut\Format\StdinSpec;
use Donut\Template;


/**
 * JSON souboru z blocks/ na objekt Block.
 *
 * Kontroluje jen strukturu jednoho souboru. Vazby mezi soubory řeší validátor.
 */
final class BlockParser
{
	/**
	 * @throws ParseException
	 */
	public function parseFile(string $path): Block
	{
		$block = $this->parseArray(JsonSource::readFile($path), $path);
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new ParseException(
				"{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		return $block;
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	public function parseArray(array $data, string $location): Block
	{
		$known = ['name', 'description', 'command', 'args', 'inputs', 'stdin', 'timeout', 'allow_failure'];
		JsonSource::rejectUnknownKeys($data, $known, $location, '');

		$name = $this->requireString($data, 'name', $location);
		$command = $this->requireString($data, 'command', $location);

		if (!isset($data['args']) || !\is_array($data['args'])) {
			throw new ParseException("{$location}: klíč 'args' je povinný a musí být pole.");
		}

		$args = [];

		foreach ($data['args'] as $i => $group) {
			if (!\is_array($group)) {
				throw new ParseException("{$location}: args[{$i}] musí být pole řetězců.");
			}

			$parsedGroup = [];

			foreach ($group as $j => $element) {
				if (!\is_string($element)) {
					throw new ParseException("{$location}: args[{$i}][{$j}] musí být řetězec.");
				}

				$parsedGroup[] = Template::parse($element);
			}

			$args[] = $parsedGroup;
		}

		$stdin = null;

		if (isset($data['stdin'])) {
			if (!\is_array($data['stdin'])) {
				throw new ParseException("{$location}: klíč 'stdin' musí být objekt.");
			}

			JsonSource::rejectUnknownKeys($data['stdin'], ['required', 'description'], $location, 'stdin');

			$stdin = new StdinSpec(
				required: isset($data['stdin']['required']) ? (bool) $data['stdin']['required'] : true,
				description: JsonSource::optionalString($data['stdin'], 'description', $location, 'stdin.description'),
			);
		}

		$timeout = null;

		if (isset($data['timeout'])) {
			if (!\is_int($data['timeout']) || $data['timeout'] < 1) {
				throw new ParseException("{$location}: 'timeout' musí být kladné celé číslo.");
			}

			$timeout = $data['timeout'];
		}

		return new Block(
			name: $name,
			command: $command,
			args: $args,
			inputs: JsonSource::parseInputs($data, $location),
			stdin: $stdin,
			timeout: $timeout,
			allowFailure: isset($data['allow_failure'])
				? JsonSource::parseAllowFailure($data['allow_failure'], $location, 'allow_failure')
				: false,
			description: JsonSource::optionalString($data, 'description', $location, 'description'),
		);
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	private function requireString(array $data, string $key, string $location): string
	{
		if (!isset($data[$key]) || !\is_string($data[$key]) || $data[$key] === '') {
			throw new ParseException("{$location}: klíč '{$key}' je povinný a musí být neprázdný řetězec.");
		}

		return $data[$key];
	}
}
