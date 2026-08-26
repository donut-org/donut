<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Format\Input;
use Donut\Format\StdinSpec;
use Donut\Template;
use Donut\Writer\BlockWriter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$writer = new BlockWriter;

// Smallest valid block: only required fields. Nothing optional may appear.
Assert::same(
	[
		'name' => 'bare',
		'command' => 'echo',
		'args' => [],
	],
	$writer->toArray(new Block(name: 'bare', command: 'echo', args: [])),
);

// A block with everything. Key order is part of the assertion — Assert::same
// compares arrays including order, so this test checks that too.
$full = new Block(
	name: 'full',
	command: 'curl',
	args: [
		[Template::parse('-sS'), Template::parse('--fail')],
		[Template::parse('--config'), Template::parse('{%curlrc%}')],
	],
	inputs: [
		'url' => new Input(name: 'url', required: true, description: 'Address'),
		// default does not occur even once in the reference workload — if the
		// writer dropped it, a round-trip over it wouldn't catch that.
		'curlrc' => new Input(name: 'curlrc', required: false, default: '/tmp/x', description: 'File'),
		'bare' => new Input(name: 'bare'),
	],
	stdin: new StdinSpec(required: false, description: 'Body'),
	timeout: 30,
	allowFailure: [0, 1],
	description: 'Block description',
);

Assert::same(
	[
		'name' => 'full',
		'description' => 'Block description',
		'command' => 'curl',
		'args' => [
			['-sS', '--fail'],
			['--config', '{%curlrc%}'],
		],
		'inputs' => [
			'url' => ['required' => true, 'description' => 'Address'],
			'curlrc' => ['required' => false, 'default' => '/tmp/x', 'description' => 'File'],
			// required is always written out, even when it's the default
			'bare' => ['required' => true],
		],
		'stdin' => ['required' => false, 'description' => 'Body'],
		'timeout' => 30,
		'allow_failure' => [0, 1],
	],
	$writer->toArray($full),
);

// stdin without a description has only required
Assert::same(
	['required' => true],
	$writer->toArray(new Block(
		name: 'x', command: 'cat', args: [],
		stdin: new StdinSpec,
	))['stdin'],
);

// allow_failure: true is written out, false is omitted
Assert::same(
	true,
	$writer->toArray(new Block(name: 'x', command: 'c', args: [], allowFailure: true))['allow_failure'],
);

Assert::false(
	\array_key_exists('allow_failure', $writer->toArray(
		new Block(name: 'x', command: 'c', args: [], allowFailure: false)
	)),
);

// empty inputs are omitted, empty args are not — args are required
$bare = $writer->toArray(new Block(name: 'x', command: 'c', args: []));
Assert::false(\array_key_exists('inputs', $bare));
Assert::true(\array_key_exists('args', $bare));

// array_map() preserves keys; args is array<int, array<int, Template>>, not
// a list, at both levels. A gap after unset() (the natural way the GUI
// deletes a group or argument) would encode as a JSON object instead of an
// array without array_values() — a gap at both nesting levels is tested
// at once.
$inner = [Template::parse('a'), Template::parse('b'), Template::parse('c')];
unset($inner[1]);

$args = [
	$inner,
	[Template::parse('x')],
	[Template::parse('y')],
];
unset($args[1]);

Assert::same(
	[
		['a', 'c'],
		['y'],
	],
	$writer->toArray(new Block(name: 'x', command: 'c', args: $args))['args'],
);
