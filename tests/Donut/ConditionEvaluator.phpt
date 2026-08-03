<?php

declare(strict_types=1);

use Donut\Format\Condition;
use Donut\Runner\ConditionEvaluator;
use Donut\Runner\RunFailedException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

function cond(string $left, string $op, ?string $right = null): Condition
{
	return new Condition(
		left: Template::parse($left),
		op: $op,
		right: $right === null ? null : Template::parse($right),
	);
}

$eval = fn(Condition $c, array $map = []) => ConditionEvaluator::evaluate($c, $map, 'at');

// řetězcové porovnání
Assert::true($eval(cond('{%A%}', 'eq', 'x'), ['A' => 'x']));
Assert::false($eval(cond('{%A%}', 'eq', 'y'), ['A' => 'x']));
Assert::true($eval(cond('{%A%}', 'neq', 'y'), ['A' => 'x']));
Assert::false($eval(cond('{%A%}', 'neq', 'x'), ['A' => 'x']));

// eq porovnává řetězce, ne čísla
Assert::false($eval(cond('{%A%}', 'eq', '1'), ['A' => '01']));

// číselné porovnání
Assert::true($eval(cond('{%A%}', 'gt', '5'), ['A' => '10']));
Assert::false($eval(cond('{%A%}', 'gt', '10'), ['A' => '10']));
Assert::true($eval(cond('{%A%}', 'gte', '10'), ['A' => '10']));
Assert::false($eval(cond('{%A%}', 'gte', '10'), ['A' => '9']));
Assert::true($eval(cond('{%A%}', 'lt', '10'), ['A' => '5']));
Assert::false($eval(cond('{%A%}', 'lt', '10'), ['A' => '10']));
Assert::true($eval(cond('{%A%}', 'lte', '5'), ['A' => '5']));
Assert::false($eval(cond('{%A%}', 'lte', '5'), ['A' => '6']));
Assert::true($eval(cond('{%A%}', 'gt', '5'), ['A' => '10.5']));
Assert::true($eval(cond('{%A%}', 'lt', '0'), ['A' => '-3']));

// contains
Assert::true($eval(cond('{%A%}', 'contains', 'bc'), ['A' => 'abcd']));
Assert::false($eval(cond('{%A%}', 'contains', 'xy'), ['A' => 'abcd']));

// empty / not_empty ignorují right
Assert::true($eval(cond('{%A%}', 'empty'), ['A' => '']));
Assert::false($eval(cond('{%A%}', 'empty'), ['A' => 'x']));
Assert::true($eval(cond('{%A%}', 'not_empty'), ['A' => 'x']));
Assert::false($eval(cond('{%A%}', 'not_empty'), ['A' => '']));

// nečíselná hodnota v číselném porovnání je chyba
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%A%}', 'gt', '5'), ['A' => 'abc'], 'w'),
	RunFailedException::class,
	'w: operátor "gt" potřebuje čísla, dostal "abc" a "5".'
);

Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%A%}', 'lt', 'x'), ['A' => '1'], 'w'),
	RunFailedException::class,
	'w: operátor "lt" potřebuje čísla, dostal "1" a "x".'
);

// binární operátor bez right — validátor to chytá dřív, runner se nesmí zhroutit
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%A%}', 'eq'), ['A' => 'x'], 'w'),
	RunFailedException::class,
	'w: operátor "eq" vyžaduje \'right\'.'
);

// neznámý operátor
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%A%}', 'matches', 'x'), ['A' => 'x'], 'w'),
	RunFailedException::class,
	'w: neznámý operátor "matches".'
);
