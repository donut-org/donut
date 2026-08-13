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

	// Assert::equal() má limit vnoření natvrdo na 10 (Assert.php:657) a strom
	// sync.json je hlubší. print_r nerozliší null/false/'' (jediné pole, kde
	// na tom záleží, je allow_failure) — serialize() je typově přesné a při
	// pádu díky Assert::same pořád ukáže skutečný rozdíl.
	Assert::same(
		\serialize($puvodni),
		\serialize($znovu),
		'round-trip workflow ' . \basename($path),
	);
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

// --- neplatné UTF-8 nesmí uniknout jako Nette\Utils\JsonException ---
// Json::encode() na neplatném UTF-8 (typicky text napsaný do GUI formuláře)
// hodí JsonException; vrstva zapisovače ji musí zabalit do WriteException,
// stejně jako FileSystem::writeAtomic() svůj Nette\IOException — jinak by ji
// volající chytající jedním catch přes Donut\Exception (viz JsonSource) propásl.
// Zápis do read-only adresáře je pro tenhle druhý případ míň spolehlivý napříč
// prostředími (uid 0 v CI permise obchází), proto se testuje jen JsonException.

Assert::exception(
	fn() => $blockWriter->writeFile(
		new Block(name: 'spatne', command: 'echo', args: [], description: "\xB1\x31"),
		$temp . '/spatne.json',
	),
	Donut\Writer\WriteException::class,
);

FileSystem::delete(TEMP_DIR);
