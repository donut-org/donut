<?php

declare(strict_types=1);

use Donut\MissingKeyException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::same('abc', Template::parse('{%a%}')->render(['a' => 'abc']));

Assert::same(
	'task-1: Fix (task-1)',
	Template::parse('{%branch%}: {%title%} ({%branch%})')
		->render(['branch' => 'task-1', 'title' => 'Fix'])
);

// an empty value is a valid value, it gets substituted
Assert::same('x=', Template::parse('x={%a%}')->render(['a' => '']));

// a single pass: {%b%} in the data is not evaluated
Assert::same('{%b%}', Template::parse('{%a%}')->render(['a' => '{%b%}', 'b' => 'no']));

// what isn't a template passes through unchanged — without any escaping
Assert::same('100% done', Template::parse('100% done')->render([]));
Assert::same('%2F%3A', Template::parse('%2F%3A')->render([]));
Assert::same('?q=%20%', Template::parse('?q=%20%')->render([]));
Assert::same('date +%Y', Template::parse('date +%Y')->render([]));
Assert::same("printf '%d\\n'", Template::parse("printf '%d\\n'")->render([]));

// real cases from the rewrite
Assert::same(
	'https://api.trello.com/1/cards/abc?list=true',
	Template::parse('https://api.trello.com/1/cards/{%shortId%}?list=true')
		->render(['shortId' => 'abc'])
);
Assert::same(
	'{"idList": "5f2"}',
	Template::parse('{"idList": "{%targetListId%}"}')->render(['targetListId' => '5f2'])
);

// a missing key is a hard error
Assert::exception(
	fn() => Template::parse('{%a%}')->render([]),
	MissingKeyException::class,
	"Key 'a' does not exist in the map."
);

$e = Assert::exception(
	fn() => Template::parse('{%something%}')->render([]),
	MissingKeyException::class
);
Assert::same('something', $e->getKey());
