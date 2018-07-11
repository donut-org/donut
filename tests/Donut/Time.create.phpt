<?php

use Tester\Assert;
use Donut\Time;

require __DIR__ . '/../bootstrap.php';


// without timezone info
Assert::same('2017-01-01 14:15:00', Time::create('2017-01-01 14:15:00')->getValue());


// with timezone info
Assert::same('2015-11-21 12:02:36', Time::create('2015-11-21T12:02:36Z')->getValue());
Assert::same('2015-11-21 10:02:36', Time::create('2015-11-21T12:02:36+02')->getValue());


// from timestamp
Assert::same('2018-07-04 17:37:31', Time::create(strtotime('Wed, 04 Jul 2018 19:37:31 +0200'))->getValue());
