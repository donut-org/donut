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


// konstantní argumenty projdou beze změny
$c = CommandLine::build(block([['-sS', '--fail']]), [], [], 'at');
Assert::same('curl', $c->command);
Assert::same(['-sS', '--fail'], $c->args);

// dosazení z mapy přes šablonu kroku
$c = CommandLine::build(
	block([['{%url%}']], ['url' => []]),
	tpl(['url' => 'https://x/{%env%}']),
	['env' => 'prod'],
	'at'
);
Assert::same(['https://x/prod'], $c->args);

// skupiny se zplošťují
$c = CommandLine::build(
	block([['-o', '{%file%}'], ['{%url%}']], ['file' => [], 'url' => []]),
	tpl(['file' => 'out.html', 'url' => 'https://x']),
	[],
	'at'
);
Assert::same(['-o', 'out.html', 'https://x'], $c->args);

// nevyplněný volitelný vstup shodí celou skupinu
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%curlrc%}'], ['{%url%}']], ['curlrc' => ['required' => false], 'url' => []]),
	tpl(['url' => 'https://x']),
	[],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// prázdná hodnota shodí skupinu stejně jako nevyplnění
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%curlrc%}'], ['{%url%}']], ['curlrc' => ['required' => false], 'url' => []]),
	tpl(['curlrc' => '{%rc%}', 'url' => 'https://x']),
	['rc' => ''],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// default se použije, když krok hodnotu nepředá
$c = CommandLine::build(
	block([['{%flags%}'], ['{%filter%}']], ['flags' => ['required' => false, 'default' => '-r'], 'filter' => []]),
	tpl(['filter' => '.id']),
	[],
	'at'
);
Assert::same(['-r', '.id'], $c->args);

// krok default přebije
$c = CommandLine::build(
	block([['{%flags%}'], ['{%filter%}']], ['flags' => ['required' => false, 'default' => '-r'], 'filter' => []]),
	tpl(['flags' => '-Rs', 'filter' => '.id']),
	[],
	'at'
);
Assert::same(['-Rs', '.id'], $c->args);

// víc proměnných a okolní text v jednom prvku
$c = CommandLine::build(
	block([['--url={%url%}&t={%tag%}']], ['url' => [], 'tag' => []]),
	tpl(['url' => 'https://x', 'tag' => 'v1']),
	[],
	'at'
);
Assert::same(['--url=https://x&t=v1'], $c->args);

// konstantní prázdný argument zůstane — není v něm proměnná
$c = CommandLine::build(block([['--prefix=']]), [], [], 'at');
Assert::same(['--prefix='], $c->args);

// povinný vstup s prázdnou hodnotou je tvrdá chyba
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => []]),
		tpl(['url' => '{%empty%}']),
		['empty' => ''],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: povinný vstup "url" kamene "b" má prázdnou hodnotu.'
);

// prázdný default u povinného vstupu je taky chyba
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => ['default' => '']]),
		[],
		[],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: povinný vstup "url" kamene "b" má prázdnou hodnotu.'
);

// šablona čtoucí neexistující klíč mapy propadne jako MissingKeyException
Assert::exception(
	fn() => CommandLine::build(
		block([['{%url%}']], ['url' => []]),
		tpl(['url' => '{%neni%}']),
		[],
		'at'
	),
	Donut\MissingKeyException::class
);
