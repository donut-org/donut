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

// --- round-trip over the reference workload ---
//
// Object → file → object. Compared via ==, which holds structurally on
// these objects, including Template.
//
// Careful: this workload alone is NOT ENOUGH. Five optional fields do not
// occur even once in it (default on inputs, timeout and allow_failure on
// a step, name on a set) — those are covered by BlockWriter.phpt and
// WorkflowWriter.phpt.

$blocks = \glob($root . '/blocks/*.json');
Assert::count(15, $blocks === false ? [] : $blocks);

foreach ($blocks === false ? [] : $blocks as $path) {
	$original = $blockParser->parseFile($path);
	$target = $temp . '/' . \basename($path);

	$blockWriter->writeFile($original, $target);
	$again = $blockParser->parseFile($target);

	Assert::equal($original, $again, 'round-trip of block ' . \basename($path));
}

$workflows = \glob($root . '/workflows/*.json');
Assert::count(4, $workflows === false ? [] : $workflows);

foreach ($workflows === false ? [] : $workflows as $path) {
	$original = $workflowParser->parseFile($path);
	$target = $temp . '/' . \basename($path);

	$workflowWriter->writeFile($original, $target);
	$again = $workflowParser->parseFile($target);

	// Assert::equal() has a hardcoded nesting limit of 10 (Assert.php:657) and
	// the sync.json tree is deeper. print_r doesn't distinguish null/false/''
	// (the only field where that matters is allow_failure) — serialize() is
	// type-precise and, on failure, Assert::same still shows the real diff.
	Assert::same(
		\serialize($original),
		\serialize($again),
		'round-trip of workflow ' . \basename($path),
	);
}

// --- the file ends with a newline ---
// All 19 files of the reference workload end that way, and git likes it.

$content = FileSystem::read($temp . '/card-dev.json');
Assert::same("\n", \substr($content, -1));

// --- the name must match the file ---
// The parser enforces it on read; the writer checks it on write, so those
// two rules can't drift out of sync.

Assert::exception(
	fn() => $blockWriter->writeFile(
		new Block(name: 'one', command: 'echo', args: []),
		$temp . '/other.json',
	),
	Donut\Writer\WriteException::class,
);

Assert::exception(
	fn() => $workflowWriter->writeFile(
		new Donut\Format\Workflow(name: 'one'),
		$temp . '/other.json',
	),
	Donut\Writer\WriteException::class,
);

// A matching name passes
$blockWriter->writeFile(new Block(name: 'correct', command: 'echo', args: []), $temp . '/correct.json');
Assert::same('correct', $blockParser->parseFile($temp . '/correct.json')->name);

// --- invalid UTF-8 must not leak out as Nette\Utils\JsonException ---
// Json::encode() on invalid UTF-8 (typically text typed into a GUI form)
// throws JsonException; the writer layer must wrap it in WriteException,
// just like it wraps FileSystem::writeAtomic()'s own Nette\IOException —
// otherwise a caller catching everything with a single catch on
// Donut\Exception (see JsonSource) would miss it. Writing to a read-only
// directory is less reliable across environments for this second case
// (uid 0 in CI bypasses the permission), so only JsonException is tested.

Assert::exception(
	fn() => $blockWriter->writeFile(
		new Block(name: 'wrong', command: 'echo', args: [], description: "\xB1\x31"),
		$temp . '/wrong.json',
	),
	Donut\Writer\WriteException::class,
);

FileSystem::delete(TEMP_DIR);
