<?php

declare(strict_types=1);

use Donut\Cli\Arguments;
use Donut\Cli\UsageException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// the first element of argv is the program name and is ignored
$a = Arguments::parse(['donut', 'card-dev', '--shortId=abc', '--model=sonnet']);
Assert::same('card-dev', $a->workflow);
Assert::same(['shortId' => 'abc', 'model' => 'sonnet'], $a->values);
Assert::false($a->help);
Assert::false($a->list);

// a value may contain an equals sign and spaces
$a = Arguments::parse(['donut', 'w', '--url=https://x/?a=1&b=2', '--message=hello world']);
Assert::same(['url' => 'https://x/?a=1&b=2', 'message' => 'hello world'], $a->values);

// an empty value is valid
$a = Arguments::parse(['donut', 'w', '--tag=']);
Assert::same(['tag' => ''], $a->values);

// flags
$a = Arguments::parse(['donut', '--list']);
Assert::true($a->list);
Assert::null($a->workflow);

$a = Arguments::parse(['donut', 'card-dev', '--help']);
Assert::true($a->help);
Assert::same('card-dev', $a->workflow);

$a = Arguments::parse(['donut', '--help']);
Assert::true($a->help);
Assert::null($a->workflow);

// a bare call
$a = Arguments::parse(['donut']);
Assert::null($a->workflow);
Assert::false($a->help);
Assert::false($a->list);
Assert::same([], $a->values);

// a repeated argument is an error
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '--tag=a', '--tag=b']),
	UsageException::class,
	'Argument --tag is given more than once.'
);

// the form without an equals sign is not supported
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '--tag', 'a']),
	UsageException::class,
	'Argument --tag must have the form --tag=value.'
);

// two workflow names
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', 'x']),
	UsageException::class,
	'Workflow is given more than once: "w" and "x".'
);

// the single-dash form is not supported
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '-t=a']),
	UsageException::class,
	'Unknown argument "-t=a".'
);

// --help and --list are flags, not inputs
Assert::exception(
	fn() => Arguments::parse(['donut', '--help=x']),
	UsageException::class,
	'Argument --help is a flag, it takes no value.'
);

Assert::exception(
	fn() => Arguments::parse(['donut', '--list=x']),
	UsageException::class,
	'Argument --list is a flag, it takes no value.'
);

// an empty key name is not a name
Assert::exception(
	fn() => Arguments::parse(['donut', '--=x']),
	UsageException::class,
	'Argument "--=x" has no key name.'
);

Assert::exception(
	fn() => Arguments::parse(['donut', '--']),
	UsageException::class,
	'Argument "--" has no key name.'
);
