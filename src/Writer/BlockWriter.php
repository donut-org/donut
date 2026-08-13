<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Block;
use Donut\Template;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;


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

		$data['args'] = \array_map(
			fn(array $group): array => \array_map(
				fn(Template $template): string => $template->getSource(),
				$group,
			),
			$block->args,
		);

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
	 * @throws WriteException když jméno kamene neodpovídá názvu souboru
	 */
	public function writeFile(Block $block, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		FileSystem::write($path, Json::encode($this->toArray($block), Json::PRETTY) . "\n");
	}
}
