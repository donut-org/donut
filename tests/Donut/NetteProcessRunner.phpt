<?php

declare(strict_types=1);

use Donut\Runner\NetteProcessRunner;
use Nette\Utils\ProcessTimeoutException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$runner = new NetteProcessRunner;

// stdout is captured and trailing newlines are stripped
$r = $runner->run('echo', ['hi'], '', true, false, 10);
Assert::same('hi', $r->stdout);
Assert::same(0, $r->exitCode);
Assert::null($r->stderr);

// internal newlines stay, only the end is stripped
$r = $runner->run('printf', ['a\nb\n\n'], '', true, false, 10);
Assert::same("a\nb", $r->stdout);

// stdin reaches the process
$r = $runner->run('cat', [], "input", true, false, 10);
Assert::same('input', $r->stdout);

// a non-zero exit code is returned, not thrown
$r = $runner->run('/usr/bin/false', [], '', true, false, 10);
Assert::same(1, $r->exitCode);
Assert::same('', $r->stdout);

// arguments aren't passed through the shell — this is one argument, not two commands
$r = $runner->run('echo', ['a; echo b'], '', true, false, 10);
Assert::same('a; echo b', $r->stdout);

// captured stderr
$r = $runner->run('sh', ['-c', 'echo error >&2'], '', true, true, 10);
Assert::same('error', $r->stderr);
Assert::same('', $r->stdout);

// stdout is streamed when the caller doesn't want it in memory
$r = $runner->run('echo', ['out'], '', false, false, 10);
Assert::null($r->stdout);
Assert::same(0, $r->exitCode);

// timeout
Assert::exception(
	fn() => $runner->run('sleep', ['5'], '', true, false, 1),
	ProcessTimeoutException::class
);
