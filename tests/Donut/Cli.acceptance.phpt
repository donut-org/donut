<?php

declare(strict_types=1);

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

file_put_contents($dir . '/blocks/upper.json', json_encode([
	'name' => 'upper', 'command' => 'tr',
	'args' => [['a-z', 'A-Z']],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/workflows/hlasite.json', json_encode([
	'name' => 'hlasite',
	'description' => 'Zvětší, co dostane.',
	'inputs' => ['kdo' => ['required' => true]],
	'steps' => [
		[
			'type' => 'run', 'block' => 'echo',
			'in' => ['text' => 'ahoj {%kdo%}'],
			'out' => ['result' => 'pozdrav'],
		],
		[
			'type' => 'run', 'block' => 'upper',
			'in' => ['stdin' => '{%pozdrav%}'],
		],
	],
]));

$bin = escapeshellarg(__DIR__ . '/../../bin/donut');

/** @return array{int, string, string} */
function donut(string $dir, string $bin, string $args): array
{
	$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$process = proc_open("php {$bin} {$args}", $descriptors, $pipes, $dir);
	Assert::type('resource', $process);
	$out = (string) stream_get_contents($pipes[1]);
	$err = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [proc_close($process), $out, $err];
}


// skutečný proces: běh projde a poslední krok vypíše na stdout
[$code, $out] = donut($dir, $bin, 'hlasite --kdo=svete');
Assert::same(0, $code);
Assert::contains('AHOJ SVETE', $out);

// seznam
[$code, $out] = donut($dir, $bin, '--list');
Assert::same(0, $code);
Assert::contains('hlasite', $out);
Assert::contains('Zvětší, co dostane.', $out);

// nápověda
[$code, $out] = donut($dir, $bin, 'hlasite --help');
Assert::same(0, $code);
Assert::contains('--kdo=', $out);

// chybějící povinný vstup: kód 2, hláška na stderr, na stdout nic
[$code, $out, $err] = donut($dir, $bin, 'hlasite');
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('kdo', $err);

// neznámý argument: kód 2
[$code, , $err] = donut($dir, $bin, 'hlasite --kdo=x --neznamy=y');
Assert::same(2, $code);
Assert::contains('neznamy', $err);

FileSystem::delete(TEMP_DIR);
