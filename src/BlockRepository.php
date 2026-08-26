<?php

declare(strict_types=1);

namespace Donut;

use Donut\Format\Block;
use Donut\Parser\BlockParser;
use Donut\Parser\NotFoundException;
use Donut\Parser\ParseException;


/**
 * Blocks from the blocks/ directory, looked up by name.
 *
 * Files are parsed lazily, but the list of names is known right away —
 * for the validator's "block does not exist" check (--list prints
 * workflows, not blocks).
 */
final class BlockRepository
{
	private readonly BlockParser $parser;

	/** @var array<string, string> name => file path */
	private array $files = [];

	/** @var array<string, Block> */
	private array $loaded = [];


	/**
	 * @throws ParseException
	 */
	public function __construct(
		string $directory,
		?BlockParser $parser = null,
	) {
		$this->parser = $parser ?? new BlockParser;

		if (!\is_dir($directory)) {
			throw new ParseException("Blocks directory '{$directory}' does not exist.");
		}

		$paths = \glob($directory . '/*.json');

		foreach ($paths === false ? [] : $paths as $path) {
			$this->files[\basename($path, '.json')] = $path;
		}

		\ksort($this->files);
	}


	public function has(string $name): bool
	{
		return isset($this->files[$name]);
	}


	/**
	 * @throws ParseException
	 */
	public function get(string $name): Block
	{
		if (!isset($this->files[$name])) {
			throw new NotFoundException("Block '{$name}' does not exist.");
		}

		return $this->loaded[$name] ??= $this->parser->parseFile($this->files[$name]);
	}


	/** @return array<int, string> */
	public function getNames(): array
	{
		return \array_keys($this->files);
	}
}
