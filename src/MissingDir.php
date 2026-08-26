<?php

declare(strict_types=1);

namespace Donut;


/**
 * What to do about a missing `workflows/` or `blocks/` directory.
 *
 * Donut does **not** create directories: silently sprinkling directories
 * onto disk is worse than an error. The message therefore has to say what
 * to do — otherwise a fresh profile is a dead end. The full path and `-p`
 * because a profile above the directory can be missing too.
 */
final class MissingDir
{
	public static function hint(string $directory): string
	{
		return 'Donut will not create it — run `mkdir -p ' . $directory . '`.';
	}
}
