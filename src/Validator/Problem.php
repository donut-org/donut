<?php

declare(strict_types=1);

namespace Donut\Validator;


final class Problem implements \Stringable
{
	public const Error = 'error';
	public const Warning = 'warning';


	public function __construct(
		public readonly string $severity,
		public readonly string $location,
		public readonly string $message,
	) {
	}


	public static function error(string $location, string $message): self
	{
		return new self(self::Error, $location, $message);
	}


	public static function warning(string $location, string $message): self
	{
		return new self(self::Warning, $location, $message);
	}


	public function __toString(): string
	{
		return "{$this->location}: {$this->message}";
	}
}
