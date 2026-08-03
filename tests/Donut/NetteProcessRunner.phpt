<?php

declare(strict_types=1);

use Donut\Runner\NetteProcessRunner;
use Nette\Utils\ProcessTimeoutException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$runner = new NetteProcessRunner;

// stdout se zachytává a koncové odřádkování se odřezává
$r = $runner->run('echo', ['ahoj'], '', false, 10);
Assert::same('ahoj', $r->stdout);
Assert::same(0, $r->exitCode);
Assert::null($r->stderr);

// vnitřní odřádkování zůstává, odřezává se jen konec
$r = $runner->run('printf', ['a\nb\n\n'], '', false, 10);
Assert::same("a\nb", $r->stdout);

// stdin doteče do procesu
$r = $runner->run('cat', [], "vstup", false, 10);
Assert::same('vstup', $r->stdout);

// nenulový exit code se vrátí, ne vyhodí
$r = $runner->run('/usr/bin/false', [], '', false, 10);
Assert::same(1, $r->exitCode);
Assert::same('', $r->stdout);

// argumenty se nepředávají přes shell — tohle je jeden argument, ne dva příkazy
$r = $runner->run('echo', ['a; echo b'], '', false, 10);
Assert::same('a; echo b', $r->stdout);

// zachycený stderr
$r = $runner->run('sh', ['-c', 'echo chyba >&2'], '', true, 10);
Assert::same('chyba', $r->stderr);
Assert::same('', $r->stdout);

// timeout
Assert::exception(
	fn() => $runner->run('sleep', ['5'], '', false, 1),
	ProcessTimeoutException::class
);
