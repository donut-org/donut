<?php

declare(strict_types=1);

use Donut\Runner\ConsoleReporter;
use Donut\Runner\NullReporter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$stream = fopen('php://memory', 'r+');
$reporter = new ConsoleReporter($stream);

$reporter->step('steps[0]', 'zjistit vlastní ID v Trellu');
$reporter->step('steps[5].steps[2]', 'board=INDEV-OSS');
$reporter->warning('w.json: klíč "x" se zapisuje a nikdy nečte');

rewind($stream);
$written = stream_get_contents($stream);
fclose($stream);

Assert::same(
	"steps[0]  zjistit vlastní ID v Trellu\n"
	. "steps[5].steps[2]  board=INDEV-OSS\n"
	. "varování: w.json: klíč \"x\" se zapisuje a nikdy nečte\n",
	$written
);

// NullReporter nesmí nic dělat a nesmí spadnout
$null = new NullReporter;
Assert::noError(function () use ($null): void {
	$null->step('steps[0]', 'x');
	$null->warning('y');
});
