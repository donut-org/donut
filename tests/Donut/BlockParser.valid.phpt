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
		['--config', '{%curlrc%}'],
		['{%url%}'],
	],
	'inputs' => [
		'url' => ['required' => true, 'description' => 'Adresa'],
		'curlrc' => ['required' => false],
	],
], 'curl-get.json');

Assert::same('curl-get', $block->name);
Assert::same('HTTP GET.', $block->description);
Assert::same('curl', $block->command);
Assert::count(3, $block->args);
Assert::same('{%url%}', $block->args[2][0]->getSource());
Assert::same(['url'], $block->args[2][0]->getKeys());

Assert::same(['url', 'curlrc'], array_keys($block->inputs));
Assert::true($block->inputs['url']->required);
Assert::same('Adresa', $block->inputs['url']->description);
Assert::false($block->inputs['curlrc']->required);
Assert::null($block->inputs['curlrc']->default);

Assert::null($block->stdin);
Assert::null($block->timeout);
Assert::false($block->allowFailure);

// defaulty: required je true, když se neuvede
$block = (new BlockParser)->parseArray([
	'name' => 'jq',
	'command' => 'jq',
	'args' => [['{%filter%}']],
	'inputs' => ['filter' => []],
	'stdin' => ['required' => true],
	'timeout' => 30,
	'allow_failure' => [0, 1],
], 'jq.json');

Assert::null($block->description);
Assert::true($block->inputs['filter']->required);
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
