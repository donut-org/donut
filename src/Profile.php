<?php

declare(strict_types=1);

namespace Donut;


/**
 * Where blocks and workflows live: `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`.
 *
 * It is a value, not disk access — it does not verify that the directories
 * exist. A missing directory is reported only by the repositories on read,
 * so the message can say what it was looking for.
 */
final class Profile
{
	public function __construct(
		private readonly string $name,
		private readonly string $dir,
	) {
	}


	/**
	 * Takes the environment as an array, not via getenv() internally —
	 * otherwise the whole table of edge cases couldn't be tested without
	 * putenv().
	 *
	 * @param  array<string, string> $env
	 * @throws Exception the environment from which the path cannot be built
	 */
	public static function fromEnvironment(array $env): self
	{
		$name = self::value($env, 'DONUT_PROFILE') ?? 'default';

		// The profile name is a directory name. A path in it would be
		// a second way to say "look elsewhere" — that's what the symlink
		// and DONUT_HOME are for.
		if (\str_contains($name, '/') || \str_contains($name, '\\') || $name === '.' || $name === '..') {
			throw new Exception(
				"DONUT_PROFILE=\"{$name}\": the profile name is a directory name, not a path."
				. ' Symlink a set that lives elsewhere in the file system.'
			);
		}

		$root = self::value($env, 'DONUT_HOME') ?? self::defaultRoot($env);

		return new self($name, $root . '/' . $name);
	}


	public function name(): string
	{
		return $this->name;
	}


	public function dir(): string
	{
		return $this->dir;
	}


	public function blocksDir(): string
	{
		return $this->dir . '/blocks';
	}


	public function workflowsDir(): string
	{
		return $this->dir . '/workflows';
	}


	/**
	 * @param  array<string, string> $env
	 * @throws Exception
	 */
	private static function defaultRoot(array $env): string
	{
		$config = self::value($env, 'XDG_CONFIG_HOME');

		if ($config !== null) {
			return $config . '/donut';
		}

		$home = self::value($env, 'HOME');

		if ($home === null) {
			throw new Exception(
				"Don't know where to look for profiles: the environment has neither HOME nor XDG_CONFIG_HOME."
				. ' Set DONUT_HOME to the profiles root.'
			);
		}

		return $home . '/.config/donut';
	}


	/**
	 * An empty value is the same as unset — the same rule the format has
	 * for unfilled inputs.
	 *
	 * @param array<string, string> $env
	 */
	private static function value(array $env, string $key): ?string
	{
		$value = $env[$key] ?? '';

		return $value === '' ? null : $value;
	}
}
