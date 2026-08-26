<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Format\Input;
use Donut\Runner\CommandLine;
use Donut\Runner\RunFailedException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/** @param array<int, array<int, string>> $args */
function block(array $args, array $inputs = []): Block
{
	$parsedArgs = [];

	foreach ($args as $group) {
		$parsedArgs[] = array_map(Template::parse(...), $group);
	}

	$parsedInputs = [];

	foreach ($inputs as $name => $spec) {
		$parsedInputs[$name] = new Input(
			name: $name,
			required: $spec['required'] ?? true,
			default: $spec['default'] ?? null,
		);
	}

	return new Block(name: 'b', command: 'curl', args: $parsedArgs, inputs: $parsedInputs);
}

/** @param array<string, string> $in */
function tpl(array $in): array
{
	return array_map(Template::parse(...), $in);
}


// constant arguments pass through unchanged
$c = CommandLine::build(block([['-sS', '--fail']]), [], [], 'at');
Assert::same('curl', $c->command);
Assert::same(['-sS', '--fail'], $c->args);

// substitution from the map via the step's template
$c = CommandLine::build(
	block([['{%url%}']], ['url' => []]),
	tpl(['url' => 'https://x/{%env%}']),
	['env' => 'prod'],
	'at'
);
Assert::same(['https://x/prod'], $c->args);

// groups get flattened
$c = CommandLine::build(
	block([['-o', '{%file%}'], ['{%url%}']], ['file' => [], 'url' => []]),
	tpl(['file' => 'out.html', 'url' => 'https://x']),
	[],
	'at'
);
Assert::same(['-o', 'out.html', 'https://x'], $c->args);

// an unfilled optional input drops the whole group
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%curlrc%}'], ['{%url%}']], ['curlrc' => ['required' => false], 'url' => []]),
	tpl(['url' => 'https://x']),
	[],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// an empty value drops the group the same as being unfilled
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%curlrc%}'], ['{%url%}']], ['curlrc' => ['required' => false], 'url' => []]),
	tpl(['curlrc' => '{%rc%}', 'url' => 'https://x']),
	['rc' => ''],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// the default is used when the step doesn't pass a value
$c = CommandLine::build(
	block([['{%flags%}'], ['{%filter%}']], ['flags' => ['required' => false, 'default' => '-r'], 'filter' => []]),
	tpl(['filter' => '.id']),
	[],
	'at'
);
Assert::same(['-r', '.id'], $c->args);

// the step overrides the default
$c = CommandLine::build(
	block([['{%flags%}'], ['{%filter%}']], ['flags' => ['required' => false, 'default' => '-r'], 'filter' => []]),
	tpl(['flags' => '-Rs', 'filter' => '.id']),
	[],
	'at'
);
Assert::same(['-Rs', '.id'], $c->args);

// multiple variables and surrounding text in one element
$c = CommandLine::build(
	block([['--url={%url%}&t={%tag%}']], ['url' => [], 'tag' => []]),
	tpl(['url' => 'https://x', 'tag' => 'v1']),
	[],
	'at'
);
Assert::same(['--url=https://x&t=v1'], $c->args);

// a constant empty argument stays — it has no variable in it
$c = CommandLine::build(block([['--prefix=']]), [], [], 'at');
Assert::same(['--prefix='], $c->args);

// a required input with an empty value is a hard error
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => []]),
		tpl(['url' => '{%empty%}']),
		['empty' => ''],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: required input "url" of block "b" has an empty value.'
);

// an empty default on a required input is also an error
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => ['default' => '']]),
		[],
		[],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: required input "url" of block "b" has an empty value.'
);

// a template reading a nonexistent map key falls through as MissingKeyException
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => []]),
		tpl(['url' => '{%missing%}']),
		[],
		'at'
	),
	Donut\MissingKeyException::class
);
