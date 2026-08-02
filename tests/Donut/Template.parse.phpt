<?php

declare(strict_types=1);

use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// prostý klíč
Assert::same(['URL'], Template::parse('{%URL%}')->getKeys());

// klíč v textu, víc klíčů, unikátnost a pořadí
Assert::same(
	['BRANCH', 'TITLE'],
	Template::parse('{%BRANCH%}: {%TITLE%} ({%BRANCH%})')->getKeys()
);

// text bez klíčů
Assert::same([], Template::parse('curl -sS')->getKeys());

// samotné procento nic neznamená -> žádný escape není potřeba
Assert::same([], Template::parse('?q=%20%')->getKeys());
Assert::same([], Template::parse('date +%Y')->getKeys());
Assert::same([], Template::parse("printf '%d\\n'")->getKeys());
Assert::same([], Template::parse('100% hotovo')->getKeys());
Assert::same([], Template::parse('?path=%2Ffoo')->getKeys());
Assert::same([], Template::parse('%2F%3A')->getKeys());

// jq filtr s objektem není šablona
Assert::same([], Template::parse('{text: .}')->getKeys());

// malá písmena i samé číslice jsou platné jméno (klíče jsou case-sensitive)
Assert::same(['url'], Template::parse('{%url%}')->getKeys());
Assert::same(['20'], Template::parse('{%20%}')->getKeys());

// sousedící šablony
Assert::same(['A', 'B'], Template::parse('{%A%}{%B%}')->getKeys());

// složená závorka kolem šablony je jen text
Assert::same(['A'], Template::parse('{{%A%}}')->getKeys());

// getSource vrací původní text
Assert::same('{%A%} b', Template::parse('{%A%} b')->getSource());

// isKeyName
Assert::true(Template::isKeyName('URL'));
Assert::true(Template::isKeyName('A1'));
Assert::true(Template::isKeyName('_A'));
Assert::true(Template::isKeyName('20'));
Assert::false(Template::isKeyName(''));
Assert::false(Template::isKeyName('A-B'));
Assert::false(Template::isKeyName('A B'));
