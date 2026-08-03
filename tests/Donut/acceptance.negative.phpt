<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = __DIR__ . '/../../docs/workflows/donut';

// kopie přepisu, do které se nastraží vady
$work = TEMP_DIR . '/rewrite';
FileSystem::copy($root, $work);

$repo = new BlockRepository($work . '/blocks');
$validator = new Validator($repo);
$parser = new WorkflowParser;

$path = $work . '/workflows/card-dev.json';

/** @return list<string> */
$errorsAfter = function (callable $break) use ($path, $parser, $validator): array {
	$data = Json::decode(FileSystem::read($path), forceArrays: true);
	$break($data);

	$result = $validator->validate($parser->parseArray($data, 'card-dev.json'));

	return array_map(strval(...), $result->getErrors());
};

// neexistující kámen
Assert::contains(
	'card-dev.json:steps[0]: kámen "curl-gett" neexistuje',
	$errorsAfter(function (array &$data): void {
		$data['steps'][0]['block'] = 'curl-gett';
	})
);

// překlep v názvu klíče
Assert::contains(
	'card-dev.json:steps[1]: šablona čte klíč "meJsn", který žádný krok nezapisuje',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['stdin'] = '{%meJsn%}';
	})
);

// nedeklarovaný vstup
Assert::contains(
	'card-dev.json:steps[1]: kámen "jq" nedeklaruje vstup "neznamy"',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['neznamy'] = 'x';
	})
);

// chybějící povinný stdin
Assert::contains(
	'card-dev.json:steps[1]: kámen "jq" vyžaduje stdin, krok ho neplní',
	$errorsAfter(function (array &$data): void {
		unset($data['steps'][1]['in']['stdin']);
	})
);

// neznámý operátor
Assert::contains(
	'card-dev.json:steps[7]: neznámý operátor "matches"',
	$errorsAfter(function (array &$data): void {
		$data['steps'][7]['condition']['op'] = 'matches';
	})
);

// vadný allow_failure v kameni
FileSystem::write(
	$work . '/blocks/test-file.json',
	Json::encode(['name' => 'test-file', 'command' => 'test', 'args' => [], 'allow_failure' => 'ano'])
);

Assert::exception(
	fn() => (new BlockRepository($work . '/blocks'))->get('test-file'),
	Donut\Parser\ParseException::class
);

FileSystem::delete(TEMP_DIR);
