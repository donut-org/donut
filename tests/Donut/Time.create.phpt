<?php

use Tester\Assert;
use Donut\Time;

require __DIR__ . '/../bootstrap.php';


// without timezone info
Assert::same('2017-01-01 14:15:00', Time::create('2017-01-01 14:15:00')->getValue());


// with timezone info
Assert::same('2015-11-21 12:02:36', Time::create('2015-11-21T12:02:36Z')->getValue());
Assert::same('2015-11-21 10:02:36', Time::create('2015-11-21T12:02:36+02')->getValue());
