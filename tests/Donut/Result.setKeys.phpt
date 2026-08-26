<?php

declare(strict_types=1);

use Donut\Validator\Result;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// M1: setKeys() may set both sets only once. Today this holds only by
// coincidence — new Result is the only call in the repository and
// validate() has no early return — a second call would silently overwrite
// both sets.

$result = new Result;
$result->setKeys(['a'], ['b']);

Assert::same(['a'], $result->getReadKeys());
Assert::same(['b'], $result->getWrittenKeys());

Assert::exception(
	fn() => $result->setKeys(['c'], ['d']),
	LogicException::class,
);

// The second call really did not overwrite anything.
Assert::same(['a'], $result->getReadKeys());
Assert::same(['b'], $result->getWrittenKeys());
