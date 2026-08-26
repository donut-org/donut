<?php

declare(strict_types=1);

use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// plain key
Assert::same(['url'], Template::parse('{%url%}')->getKeys());

// a key inside text, multiple keys, uniqueness and order
Assert::same(
	['branch', 'title'],
	Template::parse('{%branch%}: {%title%} ({%branch%})')->getKeys()
);

// text without keys
Assert::same([], Template::parse('curl -sS')->getKeys());

// a lone percent sign means nothing -> no escape is needed
Assert::same([], Template::parse('?q=%20%')->getKeys());
Assert::same([], Template::parse('date +%Y')->getKeys());
Assert::same([], Template::parse("printf '%d\\n'")->getKeys());
Assert::same([], Template::parse('100% done')->getKeys());
Assert::same([], Template::parse('?path=%2Ffoo')->getKeys());
Assert::same([], Template::parse('%2F%3A')->getKeys());

// a jq filter with an object is not a template
Assert::same([], Template::parse('{text: .}')->getKeys());

// lowercase letters and digits alone are a valid name (keys are case-sensitive)
Assert::same(['url'], Template::parse('{%url%}')->getKeys());
Assert::same(['20'], Template::parse('{%20%}')->getKeys());

// adjacent templates
Assert::same(['a', 'b'], Template::parse('{%a%}{%b%}')->getKeys());

// a curly brace around a template is just text
Assert::same(['a'], Template::parse('{{%a%}}')->getKeys());

// getSource returns the original text
Assert::same('{%a%} b', Template::parse('{%a%} b')->getSource());

// isKeyName
Assert::true(Template::isKeyName('url'));
Assert::true(Template::isKeyName('a1'));
Assert::true(Template::isKeyName('_A'));
Assert::true(Template::isKeyName('20'));
Assert::false(Template::isKeyName(''));
Assert::false(Template::isKeyName('A-B'));
Assert::false(Template::isKeyName('A B'));
