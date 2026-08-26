<?php

declare(strict_types=1);

use Donut\MissingDir;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// The hint must be runnable as-is: the full path and -p, because a profile
// above the directory can be missing too, not just the directory itself.
Assert::same(
	'Donut will not create it — run `mkdir -p /home/x/.config/donut/default/blocks`.',
	MissingDir::hint('/home/x/.config/donut/default/blocks'),
);
