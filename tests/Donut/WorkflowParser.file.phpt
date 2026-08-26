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

// a valid file gets parsed
$path = TEMP_DIR . '/demo-file.json';
file_put_contents($path, json_encode([
	'name' => 'demo-file',
	'steps' => [['type' => 'run', 'block' => 'x']],
], JSON_THROW_ON_ERROR));

$workflow = $parser->parseFile($path);
Assert::same('demo-file', $workflow->name);
Assert::count(1, $workflow->steps);

// name does not match the file name
$path = TEMP_DIR . '/wrong-name.json';
file_put_contents($path, json_encode([
	'name' => 'other',
	'steps' => [],
], JSON_THROW_ON_ERROR));

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"{$path}: name 'other' does not match the file name 'wrong-name'."
);

// non-existent / unreadable file
$path = TEMP_DIR . '/does-not-exist.json';

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"File '{$path}' cannot be read."
);

// invalid JSON
$path = TEMP_DIR . '/broken.json';
file_put_contents($path, '{not valid json');

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"File '{$path}' is not valid JSON: %a%"
);

// JSON root is not an object
$path = TEMP_DIR . '/scalar-root.json';
file_put_contents($path, '"just text"');

Assert::exception(
	fn() => $parser->parseFile($path),
	ParseException::class,
	"File '{$path}' must contain an object."
);

Helpers::purge(TEMP_DIR);
rmdir(TEMP_DIR);
