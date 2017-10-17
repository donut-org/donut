<?php

use Tester\Assert;
use Donut\Time;

require __DIR__ . '/../bootstrap.php';


test(function () {
	$a = new Time('2017-01-01 14:15:00');
	$b = new Time('2017-01-01 14:15:55');
	Assert::same(0, $a->getMinutesTo($b));
});


test(function () {
	$a = new Time('2017-01-01 14:15:20');
	$b = new Time('2017-01-01 15:00:55');
	Assert::same(45, $a->getMinutesTo($b));
});


test(function () {
	$a = new Time('2017-01-01 15:00:55');
	$b = new Time('2017-01-01 14:15:20');
	Assert::same(45, $a->getMinutesTo($b));
});
