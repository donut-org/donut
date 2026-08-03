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
Assert::true(isset($cardDev->inputs['shortId']));
Assert::false($cardDev->inputs['curlrc']->required);

$sync = $parser->parseFile($root . '/workflows/sync.json');
Assert::same('sync', $sync->name);
Assert::count(7, $sync->steps);

// jptq-task skládá `donut <workflow> --flag=…` do textového literálu, mimo
// dosah statické validace (ta zná jen {%…%} uvnitř args). Jméno přepínače
// musí být jméno vstupu, které card-dev i card-spec doopravdy deklarují —
// jinak fronta naplní úlohy, které při konzumaci spadnou na kódu 2.
$cardSpec = $parser->parseFile($root . '/workflows/card-spec.json');
$jptqTask = $repo->get('jptq-task');
$flagsChecked = 0;

foreach ($jptqTask->args as $group) {
	foreach ($group as $template) {
		if (\preg_match('~^--([A-Za-z0-9_]+)=~', $template->getSource(), $m) === 1) {
			$flag = $m[1];
			Assert::true(isset($cardDev->inputs[$flag]), "card-dev deklaruje vstup \"{$flag}\"");
			Assert::true(isset($cardSpec->inputs[$flag]), "card-spec deklaruje vstup \"{$flag}\"");
			$flagsChecked++;
		}
	}
}

Assert::same(8, $flagsChecked);

// repo-check je v přepisu kvůli větvi else: message vzniká v then i v else
// a čte se za ifem. Bez obou větví by to byl klíč zapsaný jen v jedné větvi,
// tedy varování — a tvrzení o nule varování výše by spadlo. Kdyby někdo tu
// druhou větev odstranil, musí spadnout tohle, ne až něco vzdáleného.
$repoCheck = $parser->parseFile($root . '/workflows/repo-check.json');
$branching = $repoCheck->steps[1];
Assert::type(Donut\Format\IfStep::class, $branching);
Assert::count(1, $branching->then);
Assert::count(1, $branching->else);
Assert::same('message', $branching->then[0]->key);
Assert::same('message', $branching->else[0]->key);
