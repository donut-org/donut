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
Assert::true($eval(cond('{%a%}', 'eq', 'x'), ['a' => 'x']));
Assert::false($eval(cond('{%a%}', 'eq', 'y'), ['a' => 'x']));
Assert::true($eval(cond('{%a%}', 'neq', 'y'), ['a' => 'x']));
Assert::false($eval(cond('{%a%}', 'neq', 'x'), ['a' => 'x']));

// eq porovnává řetězce, ne čísla
Assert::false($eval(cond('{%a%}', 'eq', '1'), ['a' => '01']));

// číselné porovnání
Assert::true($eval(cond('{%a%}', 'gt', '5'), ['a' => '10']));
Assert::false($eval(cond('{%a%}', 'gt', '10'), ['a' => '10']));
Assert::true($eval(cond('{%a%}', 'gte', '10'), ['a' => '10']));
Assert::false($eval(cond('{%a%}', 'gte', '10'), ['a' => '9']));
Assert::true($eval(cond('{%a%}', 'lt', '10'), ['a' => '5']));
Assert::false($eval(cond('{%a%}', 'lt', '10'), ['a' => '10']));
Assert::true($eval(cond('{%a%}', 'lte', '5'), ['a' => '5']));
Assert::false($eval(cond('{%a%}', 'lte', '5'), ['a' => '6']));
Assert::true($eval(cond('{%a%}', 'gt', '5'), ['a' => '10.5']));
Assert::true($eval(cond('{%a%}', 'lt', '0'), ['a' => '-3']));

// contains
Assert::true($eval(cond('{%a%}', 'contains', 'bc'), ['a' => 'abcd']));
Assert::false($eval(cond('{%a%}', 'contains', 'xy'), ['a' => 'abcd']));

// empty / not_empty ignorují right
Assert::true($eval(cond('{%a%}', 'empty'), ['a' => '']));
Assert::false($eval(cond('{%a%}', 'empty'), ['a' => 'x']));
Assert::true($eval(cond('{%a%}', 'not_empty'), ['a' => 'x']));
Assert::false($eval(cond('{%a%}', 'not_empty'), ['a' => '']));

// nečíselná hodnota v číselném porovnání je chyba
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%a%}', 'gt', '5'), ['a' => 'abc'], 'w'),
	RunFailedException::class,
	'w: operátor "gt" potřebuje čísla, dostal "abc" a "5".'
);

Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%a%}', 'lt', 'x'), ['a' => '1'], 'w'),
	RunFailedException::class,
	'w: operátor "lt" potřebuje čísla, dostal "1" a "x".'
);

// binární operátor bez right — validátor to chytá dřív, runner se nesmí zhroutit
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%a%}', 'eq'), ['a' => 'x'], 'w'),
	RunFailedException::class,
	'w: operátor "eq" vyžaduje \'right\'.'
);

// neznámý operátor
Assert::exception(
	fn() => ConditionEvaluator::evaluate(cond('{%a%}', 'matches', 'x'), ['a' => 'x'], 'w'),
	RunFailedException::class,
	'w: neznámý operátor "matches".'
);
