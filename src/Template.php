<?php

declare(strict_types=1);

namespace Donut;


/**
 * Text with substitution placeholders of the form {%KEY%}.
 *
 * The delimiters are two characters so they don't collide with a percent
 * sign in data: neither {% nor %} can arise from percent-encoding, that
 * would be %7B and %7D. A lone percent sign therefore has no meaning and no
 * escape exists — `date +%Y`, `printf '%d\n'` and `?path=%2Ffoo` all pass
 * through unchanged.
 */
final class Template
{
	private const KeyPattern = '[A-Za-z0-9_]+';

	/** @param list<string|array{key: string}> $segments */
	private function __construct(
		private readonly string $source,
		private readonly array $segments,
	) {
	}


	public static function parse(string $source): self
	{
		$parts = \preg_split(
			'~(\{%' . self::KeyPattern . '%\})~',
			$source,
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		if ($parts === false) {
			throw new Exception("Template '{$source}' could not be parsed.");
		}

		$segments = [];

		foreach ($parts as $part) {
			if (\preg_match('~^\{%(' . self::KeyPattern . ')%\}$~D', $part, $m) === 1) {
				$segments[] = ['key' => $m[1]];

			} else {
				$segments[] = $part;
			}
		}

		return new self($source, $segments);
	}


	public static function isKeyName(string $name): bool
	{
		return \preg_match('~^' . self::KeyPattern . '$~D', $name) === 1;
	}


	/**
	 * Keys the template reads. Unique, in order of first occurrence.
	 *
	 * @return list<string>
	 */
	public function getKeys(): array
	{
		$keys = [];

		foreach ($this->segments as $segment) {
			if (\is_array($segment) && !\in_array($segment['key'], $keys, true)) {
				$keys[] = $segment['key'];
			}
		}

		return $keys;
	}


	/**
	 * Substitutes values in a single pass. The result is not processed
	 * further, so data containing {%SOMETHING%} is not evaluated.
	 *
	 * @param  array<string, string> $map
	 * @throws MissingKeyException
	 */
	public function render(array $map): string
	{
		$out = '';

		foreach ($this->segments as $segment) {
			if (\is_array($segment)) {
				$key = $segment['key'];

				if (!\array_key_exists($key, $map)) {
					throw new MissingKeyException($key);
				}

				$out .= $map[$key];

			} else {
				$out .= $segment;
			}
		}

		return $out;
	}


	public function getSource(): string
	{
		return $this->source;
	}
}
