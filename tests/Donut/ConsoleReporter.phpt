<?php

declare(strict_types=1);

use Donut\Runner\ConsoleReporter;
use Donut\Runner\NullReporter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$stream = fopen('php://memory', 'r+');
$reporter = new ConsoleReporter($stream);

$reporter->step('steps[0]', 'find own ID in Trello');
$reporter->step('steps[5].steps[2]', 'board=INDEV-OSS');
$reporter->warning('w.json: key "x" is written and never read');

rewind($stream);
$written = stream_get_contents($stream);
fclose($stream);

Assert::same(
	"steps[0]  find own ID in Trello\n"
	. "steps[5].steps[2]  board=INDEV-OSS\n"
	. "warning: w.json: key \"x\" is written and never read\n",
	$written
);

// NullReporter must do nothing and must not crash
$null = new NullReporter;
Assert::noError(function () use ($null): void {
	$null->step('steps[0]', 'x');
	$null->warning('y');
});
