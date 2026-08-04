<?php

declare(strict_types=1);

use Donut\Cli\Arguments;
use Donut\Cli\UsageException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// první prvek argv je jméno programu a ignoruje se
$a = Arguments::parse(['donut', 'card-dev', '--shortId=abc', '--model=sonnet']);
Assert::same('card-dev', $a->workflow);
Assert::same(['shortId' => 'abc', 'model' => 'sonnet'], $a->values);
Assert::false($a->help);
Assert::false($a->list);

// hodnota smí obsahovat rovnítko i mezery
$a = Arguments::parse(['donut', 'w', '--url=https://x/?a=1&b=2', '--message=ahoj svete']);
Assert::same(['url' => 'https://x/?a=1&b=2', 'message' => 'ahoj svete'], $a->values);

// prázdná hodnota je platná
$a = Arguments::parse(['donut', 'w', '--tag=']);
Assert::same(['tag' => ''], $a->values);

// příznaky
$a = Arguments::parse(['donut', '--list']);
Assert::true($a->list);
Assert::null($a->workflow);

$a = Arguments::parse(['donut', 'card-dev', '--help']);
Assert::true($a->help);
Assert::same('card-dev', $a->workflow);

$a = Arguments::parse(['donut', '--help']);
Assert::true($a->help);
Assert::null($a->workflow);

// holé volání
$a = Arguments::parse(['donut']);
Assert::null($a->workflow);
Assert::false($a->help);
Assert::false($a->list);
Assert::same([], $a->values);

// zopakovaný argument je chyba
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '--tag=a', '--tag=b']),
	UsageException::class,
	'Argument --tag je uvedený víckrát.'
);

// tvar bez rovnítka se nepodporuje
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '--tag', 'a']),
	UsageException::class,
	'Argument --tag musí mít tvar --tag=hodnota.'
);

// dvě jména workflow
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', 'x']),
	UsageException::class,
	'Workflow je uvedené víckrát: "w" a "x".'
);

// jednopomlčkový tvar se nepodporuje
Assert::exception(
	fn() => Arguments::parse(['donut', 'w', '-t=a']),
	UsageException::class,
	'Neznámý argument "-t=a".'
);

// --help a --list jsou příznaky, ne vstupy
Assert::exception(
	fn() => Arguments::parse(['donut', '--help=x']),
	UsageException::class,
	'Argument --help je příznak, nemá hodnotu.'
);

Assert::exception(
	fn() => Arguments::parse(['donut', '--list=x']),
	UsageException::class,
	'Argument --list je příznak, nemá hodnotu.'
);

// prázdné jméno klíče není jméno
Assert::exception(
	fn() => Arguments::parse(['donut', '--=x']),
	UsageException::class,
	'Argument "--=x" nemá jméno klíče.'
);

Assert::exception(
	fn() => Arguments::parse(['donut', '--']),
	UsageException::class,
	'Argument "--" nemá jméno klíče.'
);
