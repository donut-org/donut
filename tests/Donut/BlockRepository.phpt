<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\ParseException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo',
	'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

file_put_contents($dir . '/cat.json', json_encode([
	'name' => 'cat',
	'command' => 'cat',
	'args' => [],
]));

$repo = new BlockRepository($dir);

Assert::same(['cat', 'echo'], $repo->getNames());
Assert::true($repo->has('echo'));
Assert::false($repo->has('nope'));
Assert::same('echo', $repo->get('echo')->name);
Assert::same('cat', $repo->get('cat')->command);

// stejná instance při opakovaném volání (načítá se jednou)
Assert::same($repo->get('echo'), $repo->get('echo'));

Assert::exception(
	fn() => $repo->get('nope'),
	ParseException::class,
	"Kámen 'nope' neexistuje."
);

// neexistující adresář
Assert::exception(
	fn() => new BlockRepository($dir . '/chybi'),
	ParseException::class,
	"Adresář s kameny '{$dir}/chybi' neexistuje."
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
