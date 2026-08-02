<?php

declare(strict_types=1);

use Donut\Parser\ParseException;
use Donut\Parser\WorkflowParser;
use Tester\Assert;
use Tester\Helpers;

require __DIR__ . '/../bootstrap.php';

@mkdir(TEMP_DIR, 0777, true);
Helpers::purge(TEMP_DIR);

$parser = new WorkflowParser;

// platný soubor se rozparsuje
$path = TEMP_DIR . '/demo-file.json';
file_put_contents($path, json_encode([
	'name' => 'demo-file',
	'steps' => [['type' => 'run', 'block' => 'x']],
], JSON_THROW_ON_ERROR));

$workflow = $parser->parseFile($path);
Assert::same('demo-file', $workflow->name);
Assert::count(1, $workflow->steps);

// name neodpovídá názvu souboru
$path = TEMP_DIR . '/wrong-name.json';
file_put_contents($path, json_encode([
	'name' => 'other',
	'steps' => [],
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
