<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Parser\BlockParser;
use Donut\Parser\WorkflowParser;
use Donut\Writer\BlockWriter;
use Donut\Writer\WorkflowWriter;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$blockParser = new BlockParser;
$workflowParser = new WorkflowParser;
$blockWriter = new BlockWriter;
$workflowWriter = new WorkflowWriter;

$root = __DIR__ . '/../../docs/workflows/donut';
$temp = TEMP_DIR . '/writer';
FileSystem::createDir($temp);

// --- round-trip nad referenční zátěží ---
//
// Objekt → soubor → objekt. Porovnává se přes ==, které na těchhle
// objektech drží strukturálně včetně Template.
//
// Pozor: sama tahle zátěž NESTAČÍ. Pět volitelných polí se v ní
// nevyskytuje ani jednou (default u vstupů, timeout a allow_failure
// u kroku, name u setu) — ta hlídají BlockWriter.phpt a WorkflowWriter.phpt.

$blocks = \glob($root . '/blocks/*.json');
Assert::count(15, $blocks === false ? [] : $blocks);

foreach ($blocks === false ? [] : $blocks as $path) {
	$puvodni = $blockParser->parseFile($path);
	$cil = $temp . '/' . \basename($path);

	$blockWriter->writeFile($puvodni, $cil);
	$znovu = $blockParser->parseFile($cil);

	Assert::equal($puvodni, $znovu, 'round-trip kamene ' . \basename($path));
}

$workflows = \glob($root . '/workflows/*.json');
Assert::count(4, $workflows === false ? [] : $workflows);

foreach ($workflows === false ? [] : $workflows as $path) {
	$puvodni = $workflowParser->parseFile($path);
	$cil = $temp . '/' . \basename($path);

	$workflowWriter->writeFile($puvodni, $cil);
	$znovu = $workflowParser->parseFile($cil);

	// Assert::equal() má tvrdý limit na hloubku zanoření (Nette Tester,
	// úroveň 10) a strom kroků sync.json ho i s poctivým zápisem přesahuje
	// (foreach > if > run, každý RunStep navíc nese Template s vlastním
	// segments polem). Porovnává se proto přímo přes ==, jak popisuje
	// komentář výš — Assert::equal je jen jeho hezčí obal, který tu na
	// nejhlubší workflow nejde použít.
	Assert::true($puvodni == $znovu, 'round-trip workflow ' . \basename($path));
}

// --- soubor končí novým řádkem ---
// Všech 19 souborů referenční zátěže tak končí a git to má rád.

$obsah = FileSystem::read($temp . '/card-dev.json');
Assert::same("\n", \substr($obsah, -1));

// --- jméno musí odpovídat souboru ---
// Parser to při čtení vynucuje; zapisovač to hlídá při zápisu, aby ta dvě
// pravidla nemohla přestat platit současně.

Assert::exception(
	fn() => $blockWriter->writeFile(
		new Block(name: 'jedno', command: 'echo', args: []),
		$temp . '/druhe.json',
	),
	Donut\Writer\WriteException::class,
);

Assert::exception(
	fn() => $workflowWriter->writeFile(
		new Donut\Format\Workflow(name: 'jedno'),
		$temp . '/druhe.json',
	),
	Donut\Writer\WriteException::class,
);

// Správné jméno projde
$blockWriter->writeFile(new Block(name: 'spravne', command: 'echo', args: []), $temp . '/spravne.json');
Assert::same('spravne', $blockParser->parseFile($temp . '/spravne.json')->name);

FileSystem::delete(TEMP_DIR);
