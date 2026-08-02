<?php

declare(strict_types=1);

namespace Donut;


/**
 * Šablona četla klíč, který v mapě není. Podle specifikace je to tvrdá chyba.
 */
final class MissingKeyException extends Exception
{
	public function __construct(
		private readonly string $key,
	) {
		parent::__construct("Klíč '{$key}' v mapě neexistuje.");
	}


	public function getKey(): string
	{
		return $this->key;
	}
}
