<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Format\Input;
use Donut\Format\StdinSpec;
use Donut\Template;
use Donut\Validator\BlockValidator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$validator = new BlockValidator;

// A clean block has nothing to report.
$clean = new Block(
	name: 'clean',
	command: 'curl',
	args: [[Template::parse('-sS')], [Template::parse('{%url%}')]],
	inputs: ['url' => new Input(name: 'url')],
);

Assert::same([], $validator->validate($clean)->getProblems());

// {%STDIN%} in args is an error — stdin is filled by a channel, not a template.
$withStdin = new Block(
	name: 'withStdin',
	command: 'cat',
	args: [[Template::parse('{%STDIN%}')]],
	stdin: new StdinSpec,
);

$problems = $validator->validate($withStdin)->getProblems();
Assert::count(1, $problems);
Assert::same(Donut\Validator\Problem::Error, $problems[0]->severity);
Assert::contains('{%STDIN%}', $problems[0]->message);

// An undeclared variable in args is a typo, not an unfilled value.
$unknown = new Block(
	name: 'unknown',
	command: 'curl',
	args: [[Template::parse('{%missing%}')]],
);

$problems = $validator->validate($unknown)->getProblems();
Assert::count(1, $problems);
Assert::contains('missing', $problems[0]->message);

// The default location is the block's file.
Assert::same('unknown.json', $problems[0]->location);

// A passed-in location overrides the default — this is how Validator uses it for a step.
Assert::same(
	'card-dev.json:steps[3]',
	$validator->validate($unknown, 'card-dev.json:steps[3]')->getProblems()[0]->location,
);

// Each undeclared variable is reported once, even if it appears in args more than once.
$twice = new Block(
	name: 'twice',
	command: 'echo',
	args: [[Template::parse('{%missing%}')], [Template::parse('{%missing%}')]],
);

Assert::count(1, $validator->validate($twice)->getProblems());
