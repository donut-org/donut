<?php

declare(strict_types=1);

namespace Donut;

use Donut\Format\Block;
use Donut\Parser\BlockParser;
use Donut\Parser\ParseException;


/**
 * Kameny z adresáře blocks/, hledané podle jména.
 *
 * Soubory se parsují líně, ale seznam jmen zná hned — kvůli validátorově
 * kontrole „kámen neexistuje" (--list vypisuje workflow, ne kameny).
 */
final class BlockRepository
{
	private readonly BlockParser $parser;

	/** @var array<string, string> jméno => cesta k souboru */
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
			throw new ParseException("Adresář s kameny '{$directory}' neexistuje.");
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
			throw new ParseException("Kámen '{$name}' neexistuje.");
		}

		return $this->loaded[$name] ??= $this->parser->parseFile($this->files[$name]);
	}


	/** @return array<int, string> */
	public function getNames(): array
	{
		return \array_keys($this->files);
	}
}
