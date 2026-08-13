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

// Nejmenší platný kámen: jen povinná pole. Nic volitelného se nesmí objevit.
Assert::same(
	[
		'name' => 'holy',
		'command' => 'echo',
		'args' => [],
	],
	$writer->toArray(new Block(name: 'holy', command: 'echo', args: [])),
);

// Kámen se vším. Pořadí klíčů je součástí tvrzení — Assert::same porovnává
// pole včetně pořadí, takže tenhle test hlídá i to.
$plny = new Block(
	name: 'plny',
	command: 'curl',
	args: [
		[Template::parse('-sS'), Template::parse('--fail')],
		[Template::parse('--config'), Template::parse('{%curlrc%}')],
	],
	inputs: [
		'url' => new Input(name: 'url', required: true, description: 'Adresa'),
		// default se v referenční zátěži nevyskytuje ani jednou — kdyby ho
		// zapisovač zahodil, round-trip nad ní by to nepoznal.
		'curlrc' => new Input(name: 'curlrc', required: false, default: '/tmp/x', description: 'Soubor'),
		'holy' => new Input(name: 'holy'),
	],
	stdin: new StdinSpec(required: false, description: 'Tělo'),
	timeout: 30,
	allowFailure: [0, 1],
	description: 'Popis kamene',
);

Assert::same(
	[
		'name' => 'plny',
		'description' => 'Popis kamene',
		'command' => 'curl',
		'args' => [
			['-sS', '--fail'],
			['--config', '{%curlrc%}'],
		],
		'inputs' => [
			'url' => ['required' => true, 'description' => 'Adresa'],
			'curlrc' => ['required' => false, 'default' => '/tmp/x', 'description' => 'Soubor'],
			// required se vypisuje vždycky, i když je výchozí
			'holy' => ['required' => true],
		],
		'stdin' => ['required' => false, 'description' => 'Tělo'],
		'timeout' => 30,
		'allow_failure' => [0, 1],
	],
	$writer->toArray($plny),
);

// stdin bez popisu má jen required
Assert::same(
	['required' => true],
	$writer->toArray(new Block(
		name: 'x', command: 'cat', args: [],
		stdin: new StdinSpec,
	))['stdin'],
);

// allow_failure: true se vypíše, false se vynechá
Assert::same(
	true,
	$writer->toArray(new Block(name: 'x', command: 'c', args: [], allowFailure: true))['allow_failure'],
);

Assert::false(
	\array_key_exists('allow_failure', $writer->toArray(
		new Block(name: 'x', command: 'c', args: [], allowFailure: false)
	)),
);

// prázdné inputs se vynechají, prázdné args ne — args jsou povinné
$holy = $writer->toArray(new Block(name: 'x', command: 'c', args: []));
Assert::false(\array_key_exists('inputs', $holy));
Assert::true(\array_key_exists('args', $holy));
