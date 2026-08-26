<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Runner\NetteProcessRunner;
use Donut\Runner\NullReporter;
use Donut\Runner\RunFailedException;
use Donut\Runner\Runner;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
FileSystem::createDir($dir);

file_put_contents($dir . '/typo.json', json_encode([
	'name' => 'typo',
	'command' => 'command-that-does-not-exist',
	'args' => [],
]));

$repo = new BlockRepository($dir);
$runner = new Runner($repo, new NetteProcessRunner, new NullReporter);
$workflow = (new WorkflowParser)->parseArray([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'typo']],
], 'w.json');

Assert::exception(
	fn() => $runner->run($workflow),
	RunFailedException::class,
	'%A?%w.json:steps[0]%A%typo%A%command-that-does-not-exist%A?%'
);

FileSystem::delete(TEMP_DIR);
