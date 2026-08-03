<?php

declare(strict_types=1);

use Donut\Cli\Application;
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

/**
 * Pozn.: zachytí se jen to, co píše Application — tedy --list, --help
 * a chybové hlášky. Standardní výstup spuštěných kroků jde na skutečný
 * STDOUT procesu, ne do podstrčeného streamu, takže se tady ověřit nedá.
 * Od toho je přijímací test v Cli.acceptance.phpt, který pouští donut jako
 * samostatný proces přes proc_open.
 *
 * @return array{int, string, string} kód, stdout, stderr
 */
function spust(string $dir, array $argv): array
{
	$out = fopen('php://memory', 'r+');
	$err = fopen('php://memory', 'r+');
	$code = (new Application($dir, $out, $err, ''))->run($argv);
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

// nápověda k workflow vypíše vstupy, povinnost i popis
[$code, $out] = spust($dir, ['donut', 'pozdrav', '--help']);
Assert::same(0, $code);
Assert::contains('--kdo=', $out);
Assert::contains('povinný', $out);
Assert::contains('Koho pozdravit', $out);
Assert::contains('--tag=', $out);
Assert::contains('volitelný', $out);

// běh doběhne, kód 0
[$code] = spust($dir, ['donut', 'pozdrav', '--kdo=svete']);
Assert::same(0, $code);

// selhání kroku je kód 1
[$code, , $err] = spust($dir, ['donut', 'spadne']);
Assert::same(1, $code);
Assert::contains('exit code 1', $err);

// chybějící povinný vstup je kód 2
[$code, , $err] = spust($dir, ['donut', 'pozdrav']);
Assert::same(2, $code);
Assert::contains('povinný vstup "kdo" nemá hodnotu', $err);

// neexistující workflow je kód 2
[$code, , $err] = spust($dir, ['donut', 'neexistuje']);
Assert::same(2, $code);
Assert::same("Chyba: Workflow \"neexistuje\" neexistuje.\n", $err);

// neznámý argument je kód 2
[$code, , $err] = spust($dir, ['donut', 'pozdrav', '--kdo=x', '--neznamy=y']);
Assert::same(2, $code);
Assert::same("Chyba: Workflow \"pozdrav\" nezná vstup \"neznamy\".\n", $err);

// holé volání vypíše použití a skončí dvojkou
[$code, $out] = spust($dir, ['donut']);
Assert::same(2, $code);
Assert::contains('donut', $out);

// --help bez workflow vypíše použití a skončí nulou
[$code, $out] = spust($dir, ['donut', '--help']);
Assert::same(0, $code);
Assert::contains('--list', $out);

FileSystem::delete(TEMP_DIR);
