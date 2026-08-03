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
	block([['{%URL%}']], ['URL' => []]),
	tpl(['URL' => 'https://x/{%ENV%}']),
	['ENV' => 'prod'],
	'at'
);
Assert::same(['https://x/prod'], $c->args);

// skupiny se zplošťují
$c = CommandLine::build(
	block([['-o', '{%FILE%}'], ['{%URL%}']], ['FILE' => [], 'URL' => []]),
	tpl(['FILE' => 'out.html', 'URL' => 'https://x']),
	[],
	'at'
);
Assert::same(['-o', 'out.html', 'https://x'], $c->args);

// nevyplněný volitelný vstup shodí celou skupinu
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%CURLRC%}'], ['{%URL%}']], ['CURLRC' => ['required' => false], 'URL' => []]),
	tpl(['URL' => 'https://x']),
	[],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// prázdná hodnota shodí skupinu stejně jako nevyplnění
$c = CommandLine::build(
	block([['-sS'], ['--config', '{%CURLRC%}'], ['{%URL%}']], ['CURLRC' => ['required' => false], 'URL' => []]),
	tpl(['CURLRC' => '{%RC%}', 'URL' => 'https://x']),
	['RC' => ''],
	'at'
);
Assert::same(['-sS', 'https://x'], $c->args);

// default se použije, když krok hodnotu nepředá
$c = CommandLine::build(
	block([['{%FLAGS%}'], ['{%FILTER%}']], ['FLAGS' => ['required' => false, 'default' => '-r'], 'FILTER' => []]),
	tpl(['FILTER' => '.id']),
	[],
	'at'
);
Assert::same(['-r', '.id'], $c->args);

// krok default přebije
$c = CommandLine::build(
	block([['{%FLAGS%}'], ['{%FILTER%}']], ['FLAGS' => ['required' => false, 'default' => '-r'], 'FILTER' => []]),
	tpl(['FLAGS' => '-Rs', 'FILTER' => '.id']),
	[],
	'at'
);
Assert::same(['-Rs', '.id'], $c->args);

// víc proměnných a okolní text v jednom prvku
$c = CommandLine::build(
	block([['--url={%URL%}&t={%TAG%}']], ['URL' => [], 'TAG' => []]),
	tpl(['URL' => 'https://x', 'TAG' => 'v1']),
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
		block([['{%URL%}']], ['URL' => []]),
		tpl(['URL' => '{%EMPTY%}']),
		['EMPTY' => ''],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: povinný vstup "URL" kamene "b" má prázdnou hodnotu.'
);

// prázdný default u povinného vstupu je taky chyba
Assert::exception(
	fn() => CommandLine::build(
		block([['{%URL%}']], ['URL' => ['default' => '']]),
		[],
		[],
		'w.json:steps[3]'
	),
	RunFailedException::class,
	'w.json:steps[3]: povinný vstup "URL" kamene "b" má prázdnou hodnotu.'
);

// šablona čtoucí neexistující klíč mapy propadne jako MissingKeyException
Assert::exception(
	fn() => CommandLine::build(
		block([['{%URL%}']], ['URL' => []]),
		tpl(['URL' => '{%NENI%}']),
		[],
		'at'
	),
	Donut\MissingKeyException::class
);
