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

// The names are sorted by name, and glob() alone does not do that: it sorts
// by path, so it compares "a.json" against "a.b.json" at the character after
// the dot and puts the longer name first. A block called "a.b" cannot be made
// through the GUI, whose names are [A-Za-z0-9_-]+, but these files are edited
// by hand as a matter of course — and a listing in an order nobody can
// explain reads as a bug.
file_put_contents($dir . '/a.json', json_encode(['name' => 'a', 'command' => 'true', 'args' => []]));
file_put_contents($dir . '/a.b.json', json_encode(['name' => 'a.b', 'command' => 'true', 'args' => []]));

Assert::same(['a', 'a.b', 'cat', 'echo'], (new BlockRepository($dir))->getNames());

unlink($dir . '/a.json');
unlink($dir . '/a.b.json');
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
