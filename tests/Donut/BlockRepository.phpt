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

// same instance on a repeated call (loaded once)
Assert::same($repo->get('echo'), $repo->get('echo'));

Assert::exception(
	fn() => $repo->get('nope'),
	ParseException::class,
	"Block 'nope' does not exist."
);

// nonexistent directory
Assert::exception(
	fn() => new BlockRepository($dir . '/missing'),
	ParseException::class,
	"Blocks directory '{$dir}/missing' does not exist."
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
