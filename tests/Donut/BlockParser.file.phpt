<?php

declare(strict_types=1);

use Donut\Parser\BlockParser;
use Donut\Parser\ParseException;
use Tester\Assert;
use Tester\Helpers;

require __DIR__ . '/../bootstrap.php';

@mkdir(TEMP_DIR, 0777, true);
Helpers::purge(TEMP_DIR);

$parser = new BlockParser;

// platný soubor se rozparsuje
$path = TEMP_DIR . '/curl-get.json';
file_put_contents($path, json_encode([
	'name' => 'curl-get',
	'command' => 'curl',
	'args' => [['{%url%}']],
], JSON_THROW_ON_ERROR));

$block = $parser->parseFile($path);
Assert::same('curl-get', $block->name);
Assert::same('curl', $block->command);
Assert::count(1, $block->args);
Assert::same('{%url%}', $block->args[0][0]->getSource());

// name neodpovídá názvu souboru
$path = TEMP_DIR . '/wrong-name.json';
file_put_contents($path, json_encode([
	'name' => 'other',
	'command' => 'x',
	'args' => [],
], JSON_THROW_ON_ERROR));

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"{$path}: name 'other' neodpovídá názvu souboru 'wrong-name'."
);

// neexistující / nečitelný soubor
$path = TEMP_DIR . '/does-not-exist.json';

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"Soubor '{$path}' nejde přečíst."
);

// neplatný JSON
$path = TEMP_DIR . '/broken.json';
file_put_contents($path, '{not valid json');

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"Soubor '{$path}' není platný JSON: %a%"
);

// kořen JSON není objekt
$path = TEMP_DIR . '/scalar-root.json';
file_put_contents($path, '"jen text"');

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"Soubor '{$path}' musí obsahovat objekt."
);

Helpers::purge(TEMP_DIR);
rmdir(TEMP_DIR);
