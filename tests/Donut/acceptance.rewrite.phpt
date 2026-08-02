<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = __DIR__ . '/../../docs/workflows/donut';

$repo = new BlockRepository($root . '/blocks');
$validator = new Validator($repo);
$parser = new WorkflowParser;

// všechny kameny se načtou
Assert::count(14, $repo->getNames());

foreach ($repo->getNames() as $name) {
	Assert::same($name, $repo->get($name)->name);
}

// každé workflow projde bez chyb a bez varování
$files = glob($root . '/workflows/*.json');
Assert::count(3, $files);

foreach ($files as $file) {
	$workflow = $parser->parseFile($file);
	$result = $validator->validate($workflow);

	$report = implode("\n", array_map(strval(...), $result->getProblems()));

	Assert::same([], $result->getErrors(), "chyby v {$workflow->name}:\n{$report}");
	Assert::same([], $result->getWarnings(), "varování v {$workflow->name}:\n{$report}");
}

// konkrétní očekávání, ať test nezhasne, kdyby se soubory vyprázdnily
$cardDev = $parser->parseFile($root . '/workflows/card-dev.json');
Assert::same('card-dev', $cardDev->name);
Assert::count(27, $cardDev->steps);
Assert::true(isset($cardDev->inputs['SHORT_ID']));
Assert::false($cardDev->inputs['CURLRC']->required);

$sync = $parser->parseFile($root . '/workflows/sync.json');
Assert::same('sync', $sync->name);
Assert::count(7, $sync->steps);
