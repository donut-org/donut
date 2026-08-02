<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());
