<?php

declare(strict_types=1);

use Donut\MissingKeyException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::same('abc', Template::parse('{%A%}')->render(['A' => 'abc']));

Assert::same(
	'task-1: Oprava (task-1)',
	Template::parse('{%BRANCH%}: {%TITLE%} ({%BRANCH%})')
		->render(['BRANCH' => 'task-1', 'TITLE' => 'Oprava'])
);

// prázdná hodnota je platná hodnota, dosadí se
Assert::same('x=', Template::parse('x={%A%}')->render(['A' => '']));

// jeden průchod: {%B%} v datech se nevyhodnotí
Assert::same('{%B%}', Template::parse('{%A%}')->render(['A' => '{%B%}', 'B' => 'ne']));

// co není šablona, projde beze změny — bez jakéhokoliv escapování
Assert::same('100% hotovo', Template::parse('100% hotovo')->render([]));
Assert::same('%2F%3A', Template::parse('%2F%3A')->render([]));
Assert::same('?q=%20%', Template::parse('?q=%20%')->render([]));
Assert::same('date +%Y', Template::parse('date +%Y')->render([]));
Assert::same("printf '%d\\n'", Template::parse("printf '%d\\n'")->render([]));

// reálné případy z přepisu
Assert::same(
	'https://api.trello.com/1/cards/abc?list=true',
	Template::parse('https://api.trello.com/1/cards/{%SHORT_ID%}?list=true')
		->render(['SHORT_ID' => 'abc'])
);
Assert::same(
	'{"idList": "5f2"}',
	Template::parse('{"idList": "{%TARGET_LIST_ID%}"}')->render(['TARGET_LIST_ID' => '5f2'])
);

// chybějící klíč je tvrdá chyba
Assert::exception(
	fn() => Template::parse('{%A%}')->render([]),
	MissingKeyException::class,
	"Klíč 'A' v mapě neexistuje."
);

$e = Assert::exception(
	fn() => Template::parse('{%NECO%}')->render([]),
	MissingKeyException::class
);
Assert::same('NECO', $e->getKey());
