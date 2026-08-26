<?php

declare(strict_types=1);

use Donut\Cli\UsageException;
use Donut\Parser\ParseException;
use Donut\Runner\CannotStartException;
use Donut\Runner\RunFailedException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * The order of catch blocks in Cli\Application, and the translation of
 * exceptions to exit codes, rest on this — a caller who doesn't care about
 * the difference must be able to catch anything with a single catch
 * (Donut\Exception).
 */
Assert::exception(fn() => throw new ParseException('bum'), Donut\Exception::class, 'bum');
Assert::exception(fn() => throw new UsageException('bum'), Donut\Exception::class, 'bum');
Assert::exception(fn() => throw new RunFailedException('bum'), Donut\Exception::class, 'bum');

// CannotStartException is a subclass of RunFailedException, not a separate
// type — a caller that only catches RunFailedException catches it too
Assert::true(new CannotStartException('x') instanceof RunFailedException);
