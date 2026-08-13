<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Input;


/**
 * Vstupy do pole. Používají ho oba zapisovače — kámen i workflow mají
 * `inputs` ve stejném tvaru, takže to pravidlo má jedno místo.
 */
final class InputWriter
{
	/**
	 * @param  array<string, Input> $inputs
	 * @return array<string, array<string, mixed>>
	 */
	public static function toArray(array $inputs): array
	{
		$data = [];

		foreach ($inputs as $name => $input) {
			// required se vypisuje vždycky, i když je výchozí — je tak
			// u všech vstupů referenční zátěže a uložením se soubor
			// nemá měnit víc, než je nutné.
			$spec = ['required' => $input->required];

			if ($input->default !== null) {
				$spec['default'] = $input->default;
			}

			if ($input->description !== null) {
				$spec['description'] = $input->description;
			}

			$data[$name] = $spec;
		}

		return $data;
	}
}
