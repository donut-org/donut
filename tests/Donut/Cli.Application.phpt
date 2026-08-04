<?php

declare(strict_types=1);

use Donut\Cli\Application;
use Donut\Runner\ProcessResult;
use Donut\Runner\ProcessRunner;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/home';
FileSystem::createDir($dir . '/blocks');
FileSystem::createDir($dir . '/workflows');

file_put_contents($dir . '/blocks/echo.json', json_encode([
	'name' => 'echo', 'command' => 'echo',
	'args' => [['{%text%}']],
	'inputs' => ['text' => ['required' => true]],
]));

file_put_contents($dir . '/blocks/fail.json', json_encode([
	'name' => 'fail', 'command' => '/usr/bin/false', 'args' => [],
]));

file_put_contents($dir . '/workflows/pozdrav.json', json_encode([
	'name' => 'pozdrav',
	'description' => 'Pozdraví.',
	'inputs' => [
		'kdo' => ['required' => true, 'description' => 'Koho pozdravit'],
		'tag' => ['required' => false, 'default' => 'ahoj', 'description' => 'Pozdrav'],
	],
	'steps' => [[
		'type' => 'run', 'block' => 'echo',
		'in' => ['text' => '{%tag%} {%kdo%}'],
	]],
]));

file_put_contents($dir . '/workflows/spadne.json', json_encode([
	'name' => 'spadne',
	'description' => 'Vždycky selže.',
	'steps' => [['type' => 'run', 'block' => 'fail']],
]));

file_put_contents($dir . '/workflows/bez-defaultu.json', json_encode([
	'name' => 'bez-defaultu',
	'description' => 'Volitelný vstup bez default.',
	'inputs' => [
		'tag' => ['required' => false],
	],
	'steps' => [
		['type' => 'set', 'key' => 'precteno', 'value' => '{%tag%}'],
	],
]));

/**
 * Pozn.: zachytí se jen to, co píše Application — tedy --list, --help
 * a chybové hlášky. Standardní výstup spuštěných kroků jde na skutečný
 * STDOUT procesu, ne do podstrčeného streamu, takže se tady ověřit nedá.
 * Od toho je přijímací test v Cli.acceptance.phpt, který pouští donut jako
 * samostatný proces přes proc_open.
 *
 * @param  array<int, string> $argv
 * @return array{int, string, string} kód, stdout, stderr
 */
function spust(string $dir, array $argv, ?ProcessRunner $processes = null): array
{
	$out = fopen('php://memory', 'r+');
	$err = fopen('php://memory', 'r+');
	$code = (new Application($dir, $out, $err, '', $processes))->run($argv);
	rewind($out);
	rewind($err);
	$result = [$code, stream_get_contents($out), stream_get_contents($err)];
	fclose($out);
	fclose($err);

	return $result;
}


// --list vypíše workflow s popisem, abecedně
[$code, $out] = spust($dir, ['donut', '--list']);
Assert::same(0, $code);
Assert::contains('pozdrav', $out);
Assert::contains('Pozdraví.', $out);
Assert::contains('spadne', $out);
Assert::true(strpos($out, 'pozdrav') < strpos($out, 'spadne'));

// nápověda k workflow vypíše vstupy, povinnost i popis — u toho, ke kterému
// patří, ne jen někde ve výstupu; jinak by prohozený ternář v Application
// (povinný <-> volitelný) test neshodil
[$code, $out] = spust($dir, ['donut', 'pozdrav', '--help']);
Assert::same(0, $code);

$radky = \explode("\n", $out);
$kdo = null;
$tag = null;

foreach ($radky as $radek) {
	if (\str_contains($radek, '--kdo=')) {
		$kdo = $radek;
	} elseif (\str_contains($radek, '--tag=')) {
		$tag = $radek;
	}
}

Assert::notNull($kdo, 'řádek s --kdo= existuje');
Assert::contains('povinný', $kdo);
Assert::notContains('volitelný', $kdo);
Assert::contains('Koho pozdravit', $kdo);

Assert::notNull($tag, 'řádek s --tag= existuje');
Assert::contains('volitelný', $tag);
Assert::notContains('povinný', $tag);

// běh doběhne, kód 0
[$code] = spust($dir, ['donut', 'pozdrav', '--kdo=svete']);
Assert::same(0, $code);

// nepředaný volitelný vstup bez default se čte jako '' — ne kód 1
[$code] = spust($dir, ['donut', 'bez-defaultu']);
Assert::same(0, $code);

// selhání kroku je kód 1
[$code, , $err] = spust($dir, ['donut', 'spadne']);
Assert::same(1, $code);
Assert::contains('exit code 1', $err);

// chybějící povinný vstup je kód 2
[$code, , $err] = spust($dir, ['donut', 'pozdrav']);
Assert::same(2, $code);
Assert::contains('povinný vstup "kdo" nemá hodnotu', $err);

// prázdný povinný vstup je totéž co nevyplněný — kód 2, ne rozjetý běh
[$code, , $err] = spust($dir, ['donut', 'pozdrav', '--kdo=']);
Assert::same(2, $code);
Assert::contains('povinný vstup "kdo" nemá hodnotu', $err);

// neexistující workflow je kód 2 a hláška řekne, kde se hledalo — pracovní
// adresář je nejostřejší hrana nástroje a nejčastější příčina téhle chyby
[$code, , $err] = spust($dir, ['donut', 'neexistuje']);
Assert::same(2, $code);
Assert::contains('Workflow "neexistuje" neexistuje.', $err);
Assert::contains($dir . '/workflows/', $err);

// neznámý argument je kód 2
[$code, , $err] = spust($dir, ['donut', 'pozdrav', '--kdo=x', '--neznamy=y']);
Assert::same(2, $code);
Assert::same("Chyba: Workflow \"pozdrav\" nezná vstup \"neznamy\".\n", $err);

// holé volání je chyba — použití jde na stderr a stdout zůstává prázdný
[$code, $out, $err] = spust($dir, ['donut']);
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('donut --list', $err);

// --help bez workflow vypíše použití a skončí nulou — na stdout, není to chyba
[$code, $out, $err] = spust($dir, ['donut', '--help']);
Assert::same(0, $code);
Assert::contains('--list', $out);
Assert::same('', $err);

// --list přežije vadný soubor: dobrá workflow jdou na stdout, vadné se hlásí
// na stderr a kód je 2. Jeden rozbitý soubor nesmí schovat ostatní — zvlášť
// ne ve chvíli, kdy je adresář rozdělaný a člověk potřebuje vidět, co má.
file_put_contents($dir . '/workflows/rozbite.json', '{ tohle není JSON');

[$code, $out, $err] = spust($dir, ['donut', '--list']);
Assert::same(2, $code);
Assert::contains('pozdrav', $out);
Assert::contains('spadne', $out);
Assert::notContains('rozbite', $out);
Assert::contains('rozbite.json', $err);

unlink($dir . '/workflows/rozbite.json');

// neočekávaná Donut\Exception se zachytí a skončí dvojkou. Dnes ji nic nehází,
// takže se musí podstrčit — jinak by ta větev nešla spustit vůbec.
$vybuchne = new class implements ProcessRunner {
	/**
	 * @param list<string> $args
	 */
	public function run(
		string $command,
		array $args,
		string $stdin,
		bool $captureStdout,
		bool $captureStderr,
		?int $timeout,
	): ProcessResult
	{
		throw new Donut\Exception('rozbité vnitřnosti');
	}
};

[$code, , $err] = spust($dir, ['donut', 'pozdrav', '--kdo=svete'], $vybuchne);
Assert::same(2, $code);
Assert::contains('Vnitřní chyba nástroje: rozbité vnitřnosti', $err);

FileSystem::delete(TEMP_DIR);
