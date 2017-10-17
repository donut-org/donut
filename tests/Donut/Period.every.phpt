<?php

use Tester\Assert;
use Donut\Period;

require __DIR__ . '/../bootstrap.php';


test(function () {
	Assert::same(60, Period::every('1h')->getInterval());
	Assert::same(120, Period::every('2h')->getInterval());
	Assert::same(150, Period::every('2h 30m')->getInterval());
	Assert::same(5, Period::every('5')->getInterval());
	Assert::same(5, Period::every('5m')->getInterval());
});
