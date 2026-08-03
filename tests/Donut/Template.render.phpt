<?php

declare(strict_types=1);

use Donut\MissingKeyException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::same('abc', Template::parse('{%a%}')->render(['a' => 'abc']));

Assert::same(
	'task-1: Oprava (task-1)',
	Template::parse('{%branch%}: {%title%} ({%branch%})')
		->render(['branch' => 'task-1', 'title' => 'Oprava'])
);

// prázdná hodnota je platná hodnota, dosadí se
Assert::same('x=', Template::parse('x={%a%}')->render(['a' => '']));

// jeden průchod: {%b%} v datech se nevyhodnotí
Assert::same('{%b%}', Template::parse('{%a%}')->render(['a' => '{%b%}', 'b' => 'ne']));

// co není šablona, projde beze změny — bez jakéhokoliv escapování
Assert::same('100% hotovo', Template::parse('100% hotovo')->render([]));
Assert::same('%2F%3A', Template::parse('%2F%3A')->render([]));
Assert::same('?q=%20%', Template::parse('?q=%20%')->render([]));
Assert::same('date +%Y', Template::parse('date +%Y')->render([]));
Assert::same("printf '%d\\n'", Template::parse("printf '%d\\n'")->render([]));

// reálné případy z přepisu
Assert::same(
	'https://api.trello.com/1/cards/abc?list=true',
	Template::parse('https://api.trello.com/1/cards/{%shortId%}?list=true')
		->render(['shortId' => 'abc'])
);
Assert::same(
	'{"idList": "5f2"}',
	Template::parse('{"idList": "{%targetListId%}"}')->render(['targetListId' => '5f2'])
);

// chybějící klíč je tvrdá chyba
Assert::exception(
	fn() => Template::parse('{%a%}')->render([]),
	MissingKeyException::class,
	"Klíč 'a' v mapě neexistuje."
);

$e = Assert::exception(
	fn() => Template::parse('{%neco%}')->render([]),
	MissingKeyException::class
);
Assert::same('neco', $e->getKey());
