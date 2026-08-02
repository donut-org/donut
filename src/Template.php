<?php

declare(strict_types=1);

namespace Donut;


/**
 * Text s dosazovacími místy tvaru {%KLIC%}.
 *
 * Delimitery jsou dvouznakové, aby se nesrazily s procentem v datech: {% ani
 * %} nevznikne percent-encodingem, byly by to %7B a %7D. Samotné procento
 * proto nemá význam a žádný escape neexistuje — `date +%Y`, `printf '%d\n'`
 * i `?path=%2Ffoo` projdou beze změny.
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
			throw new Exception("Šablonu '{$source}' se nepodařilo rozparsovat.");
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
	 * Klíče, které šablona čte. Unikátní, v pořadí prvního výskytu.
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
	 * Dosadí hodnoty jedním průchodem. Výsledek se dál nezpracovává, takže
	 * data obsahující {%NECO%} se nevyhodnocují.
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
