<?php

declare(strict_types=1);

use Donut\Parser\BlockParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$block = (new BlockParser)->parseArray([
	'name' => 'curl-get',
	'description' => 'HTTP GET.',
	'command' => 'curl',
	'args' => [
		['-sS', '--fail'],
		['--config', '{%CURLRC%}'],
		['{%URL%}'],
	],
	'inputs' => [
		'URL' => ['required' => true, 'description' => 'Adresa'],
		'CURLRC' => ['required' => false],
	],
], 'curl-get.json');

Assert::same('curl-get', $block->name);
Assert::same('HTTP GET.', $block->description);
Assert::same('curl', $block->command);
Assert::count(3, $block->args);
Assert::same('{%URL%}', $block->args[2][0]->getSource());
Assert::same(['URL'], $block->args[2][0]->getKeys());

Assert::same(['URL', 'CURLRC'], array_keys($block->inputs));
Assert::true($block->inputs['URL']->required);
Assert::same('Adresa', $block->inputs['URL']->description);
Assert::false($block->inputs['CURLRC']->required);
Assert::null($block->inputs['CURLRC']->default);

Assert::null($block->stdin);
Assert::null($block->timeout);
Assert::false($block->allowFailure);

// defaulty: required je true, když se neuvede
$block = (new BlockParser)->parseArray([
	'name' => 'jq',
	'command' => 'jq',
	'args' => [['{%FILTER%}']],
	'inputs' => ['FILTER' => []],
	'stdin' => ['required' => true],
	'timeout' => 30,
	'allow_failure' => [0, 1],
], 'jq.json');

Assert::null($block->description);
Assert::true($block->inputs['FILTER']->required);
Assert::notNull($block->stdin);
Assert::true($block->stdin->required);
Assert::same(30, $block->timeout);
Assert::same([0, 1], $block->allowFailure);

// allow_failure: true
$block = (new BlockParser)->parseArray([
	'name' => 'x',
	'command' => 'x',
	'args' => [],
	'allow_failure' => true,
], 'x.json');

Assert::true($block->allowFailure);
Assert::same([], $block->inputs);
