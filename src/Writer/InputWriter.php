<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Input;


/**
 * Inputs to an array. Both writers use it — a block and a workflow have
 * `inputs` in the same shape, so that rule lives in one place.
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
			// required is always written out, even when it's the default —
			// it is that way for every input in the reference workload, and
			// saving must not change the file more than necessary.
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
