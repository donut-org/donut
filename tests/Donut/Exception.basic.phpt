<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::exception(
	fn() => throw new Donut\Exception('bum'),
	Donut\Exception::class,
	'bum'
);
