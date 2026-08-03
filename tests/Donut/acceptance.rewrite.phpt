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
Assert::count(15, $repo->getNames());

foreach ($repo->getNames() as $name) {
	Assert::same($name, $repo->get($name)->name);
}

// každé workflow projde bez chyb a bez varování
$files = glob($root . '/workflows/*.json');
Assert::count(4, $files);

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

// repo-check je v přepisu kvůli větvi else: MESSAGE vzniká v then i v else
// a čte se za ifem. Bez obou větví by to byl klíč zapsaný jen v jedné větvi,
// tedy varování — a tvrzení o nule varování výše by spadlo. Kdyby někdo tu
// druhou větev odstranil, musí spadnout tohle, ne až něco vzdáleného.
$repoCheck = $parser->parseFile($root . '/workflows/repo-check.json');
$branching = $repoCheck->steps[1];
Assert::type(Donut\Format\IfStep::class, $branching);
Assert::count(1, $branching->then);
Assert::count(1, $branching->else);
Assert::same('MESSAGE', $branching->then[0]->key);
Assert::same('MESSAGE', $branching->else[0]->key);
