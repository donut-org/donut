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

// a copy of the rewrite, into which defects get planted
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

// a nonexistent block
Assert::contains(
	'card-dev.json:steps[0]: block "curl-gett" does not exist',
	$errorsAfter(function (array &$data): void {
		$data['steps'][0]['block'] = 'curl-gett';
	})
);

// a typo in the key name
Assert::contains(
	'card-dev.json:steps[1]: template reads key "meJsn", which no step writes',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['stdin'] = '{%meJsn%}';
	})
);

// an undeclared input
Assert::contains(
	'card-dev.json:steps[1]: block "jq" does not declare input "unknown"',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['unknown'] = 'x';
	})
);

// a missing required stdin
Assert::contains(
	'card-dev.json:steps[1]: block "jq" requires stdin, the step does not fill it',
	$errorsAfter(function (array &$data): void {
		unset($data['steps'][1]['in']['stdin']);
	})
);

// an unknown operator
Assert::contains(
	'card-dev.json:steps[7]: unknown operator "matches"',
	$errorsAfter(function (array &$data): void {
		$data['steps'][7]['condition']['op'] = 'matches';
	})
);

// a bad allow_failure in a block
FileSystem::write(
	$work . '/blocks/test-file.json',
	Json::encode(['name' => 'test-file', 'command' => 'test', 'args' => [], 'allow_failure' => 'yes'])
);

Assert::exception(
	fn() => (new BlockRepository($work . '/blocks'))->get('test-file'),
	Donut\Parser\ParseException::class
);

FileSystem::delete(TEMP_DIR);
