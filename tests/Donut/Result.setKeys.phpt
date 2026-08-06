<?php

declare(strict_types=1);

use Donut\Validator\Result;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// M1: setKeys() smí nastavit obě množiny jen jednou. Dnes to drží jen shodou
// okolností — new Result je v repozitáři jediné volání a validate() nemá
// časný return — druhé volání by tiše přepsalo obě množiny.

$result = new Result;
$result->setKeys(['a'], ['b']);

Assert::same(['a'], $result->getReadKeys());
Assert::same(['b'], $result->getWrittenKeys());

Assert::exception(
	fn() => $result->setKeys(['c'], ['d']),
	LogicException::class,
);

// Druhé volání skutečně nic nepřepsalo.
Assert::same(['a'], $result->getReadKeys());
Assert::same(['b'], $result->getWrittenKeys());
