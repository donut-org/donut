<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Block;
use Donut\Template;
use Nette\IOException;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Block to an array. Inverse of BlockParser.
 *
 * The key order matches how the files are written today, so saving changes
 * as little as possible. Optional fields are omitted when unfilled — with
 * the single exception of `required`, which is always written out.
 */
final class BlockWriter
{
	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Block $block): array
	{
		$data = ['name' => $block->name];

		if ($block->description !== null) {
			$data['description'] = $block->description;
		}

		$data['command'] = $block->command;

		// array_map() preserves keys; args is array<int, array<int, Template>>,
		// not a list, so a gap in one of the arrays (e.g. after unset() in the
		// GUI) would encode as a JSON object instead of an array without
		// array_values().
		$data['args'] = \array_values(\array_map(
			fn(array $group): array => \array_values(\array_map(
				fn(Template $template): string => $template->getSource(),
				$group,
			)),
			$block->args,
		));

		if ($block->inputs !== []) {
			$data['inputs'] = InputWriter::toArray($block->inputs);
		}

		if ($block->stdin !== null) {
			$stdin = ['required' => $block->stdin->required];

			if ($block->stdin->description !== null) {
				$stdin['description'] = $block->stdin->description;
			}

			$data['stdin'] = $stdin;
		}

		if ($block->timeout !== null) {
			$data['timeout'] = $block->timeout;
		}

		// For a block, false is the default value, not "unset" — unlike a
		// step, where the default is null and false means a deliberate opt-out.
		if ($block->allowFailure !== false) {
			$data['allow_failure'] = $block->allowFailure;
		}

		return $data;
	}


	/**
	 * The path comes from outside, not derived from the name: the repository
	 * already holds it for every known name, and a second reading of the same
	 * rule could diverge from it. It is checked that they agree, though — the
	 * parser enforces it too when reading.
	 *
	 * @throws WriteException when the block's name does not match the file
	 *                        name, the data cannot be encoded to JSON, or the
	 *                        file cannot be written
	 */
	public function writeFile(Block $block, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$block->name}' does not match the file name '{$expected}'."
			);
		}

		try {
			$content = Json::encode($this->toArray($block), Json::PRETTY) . "\n";

		} catch (JsonException $e) {
			throw new WriteException("{$path}: data could not be encoded to JSON: {$e->getMessage()}", 0, $e);
		}

		try {
			FileSystem::writeAtomic($path, $content);

		} catch (IOException $e) {
			throw new WriteException("{$path}: file could not be written: {$e->getMessage()}", 0, $e);
		}
	}
}
