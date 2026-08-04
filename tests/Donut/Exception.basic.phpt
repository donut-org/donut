<?php

declare(strict_types=1);

use Donut\Cli\UsageException;
use Donut\Parser\ParseException;
use Donut\Runner\CannotStartException;
use Donut\Runner\RunFailedException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * Na tomhle stojí pořadí catch bloků v Cli\Application a překlad výjimek
 * na návratové kódy — volající, kterého liší nezajímají, musí umět
 * chytit cokoliv jedním catch (Donut\Exception).
 */
Assert::exception(fn() => throw new ParseException('bum'), Donut\Exception::class, 'bum');
Assert::exception(fn() => throw new UsageException('bum'), Donut\Exception::class, 'bum');
Assert::exception(fn() => throw new RunFailedException('bum'), Donut\Exception::class, 'bum');

// CannotStartException je podtřída RunFailedException, ne samostatný typ —
// volající, který chytá jen RunFailedException, chytí i ji
Assert::true(new CannotStartException('x') instanceof RunFailedException);
