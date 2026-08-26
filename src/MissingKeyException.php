<?php

declare(strict_types=1);

namespace Donut;


/**
 * The template read a key that is not in the map. Per the spec this is
 * a hard error.
 */
final class MissingKeyException extends Exception
{
	public function __construct(
		private readonly string $key,
	) {
		parent::__construct("Key '{$key}' does not exist in the map.");
	}


	public function getKey(): string
	{
		return $this->key;
	}
}
