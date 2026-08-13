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
 * Block na pole. Inverze BlockParseru.
 *
 * Pořadí klíčů odpovídá tomu, jak jsou soubory psané dnes, aby se uložením
 * změnily co nejmíň. Volitelná pole se vynechávají, když nejsou vyplněná —
 * s jedinou výjimkou `required`, které se vypisuje vždycky.
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

		// array_map() zachovává klíče; args je array<int, array<int, Template>>,
		// ne list, takže mezera v jednom z polí (např. po unset() v GUI) by se
		// bez array_values() zakódovala jako JSON objekt místo pole.
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

		// U kamene je false výchozí hodnota, ne „nenastaveno" — na rozdíl
		// od kroku, kde je výchozí null a false znamená vědomé vypnutí.
		if ($block->allowFailure !== false) {
			$data['allow_failure'] = $block->allowFailure;
		}

		return $data;
	}


	/**
	 * Cesta se dostává zvenčí, neodvozuje se ze jména: repository už ji pro
	 * každé známé jméno drží a druhý výklad téhož pravidla by se s ním mohl
	 * rozejít. Kontroluje se ale, že spolu sedí — parser to při čtení
	 * vynucuje taky.
	 *
	 * @throws WriteException když jméno kamene neodpovídá názvu souboru, data
	 *                        nejde zakódovat do JSON, nebo soubor nejde zapsat
	 */
	public function writeFile(Block $block, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		try {
			$content = Json::encode($this->toArray($block), Json::PRETTY) . "\n";

		} catch (JsonException $e) {
			throw new WriteException("{$path}: data se nepodařilo zakódovat do JSON: {$e->getMessage()}", 0, $e);
		}

		try {
			FileSystem::writeAtomic($path, $content);

		} catch (IOException $e) {
			throw new WriteException("{$path}: soubor nejde zapsat: {$e->getMessage()}", 0, $e);
		}
	}
}
