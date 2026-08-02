# Parser + validátor formátu donut — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Načíst kameny a workflow z JSON souborů do typovaných objektů a před spuštěním staticky ověřit, že jsou zapojené správně.

**Architecture:** Tři vrstvy bez cyklických závislostí. `Format\*` jsou neměnné hodnotové objekty popisující, co v souborech stálo. `Parser\*` je převádí z pole z `Json::decode` a hlásí strukturální vady. `Validator\*` kontroluje věci napříč soubory — existenci kamenů, naplnění vstupů a tok klíčů mapou. `Template` stojí stranou, používá ho parser i validátor a později runner.

**Tech Stack:** PHP 8.1+, nette/utils ^4.1.4 (`Json`, `Strings`, `FileSystem`), nette/tester ^2.6, PHPStan level max.

## Global Constraints

- PHP `>= 8.1`. Používej typované vlastnosti, `readonly`, enumy, konstruktorovou promoci.
- `nette/utils` `^4.1.4`. JSON se dekóduje **jen** přes `Nette\Utils\Json::decode($s, forceArrays: true)`.
- Žádná databáze. Žádné contributte balíčky. Žádný `nette/di`.
- Namespace `Donut\`, PSR-4 na `src/`.
- PHPStan level max nad `src` i `tests` musí projít. **Pozor:** hodnoty z `Json::decode` jsou `mixed` a level max odmítne `(string) $mixed` s „Cannot cast mixed to string". Nepovinné textové hodnoty proto neprocházejí přímým přetypováním, ale přes `JsonSource::optionalString()` — viz Task 3.
- **Strukturální neshoda je vždy `ParseException`, nikdy tichá `null`.** Když je v souboru na místě textu pole nebo objekt, běh se zastaví s hláškou. Nejvíc na tom záleží u `default`, který se za běhu dosazuje — tiše zahozený default by se projevil až chybějícím argumentem někde daleko.
- Testy jsou `.phpt` soubory pro nette/tester, spouštěné přes `make test`.
- Jazyk kódu a identifikátorů je angličtina. Chybové hlášky pro uživatele česky, protože specifikace i workflow jsou česky.
- **Referenční pravda je `docs/format-specifikace.md` verze 0.3.** Když se plán a specifikace rozejdou, platí specifikace a rozpor nahlas oznam.
- Šablona má tvar `{%KLIC%}`, jméno klíče je `[A-Za-z0-9_]+`. Regulární výraz: `\{%([A-Za-z0-9_]+)%\}`. **Escape neexistuje** — samotné procento nemá význam, takže `date +%Y`, `printf '%d\n'` i `?path=%2Ffoo` procházejí beze změny.
- Tenhle plán **nedělá runner ani CLI**. Nic nespouští, `Nette\Utils\Process` se v něm neobjeví.

---

### Task 1: Vyprázdnit balíček a rozběhnout testy

Balíček dnes obsahuje nesouvisející kód (publikování na Twitter/Facebook/Instagram z RSS), závislosti z roku 2018 a `composer.lock` starý osm let. Celý `src/` a `tests/` jde pryč — historie zůstává v gitu.

**Files:**
- Modify: `composer.json` (celý obsah nahradit)
- Delete: `src/` (celý adresář), `tests/Donut/`, `composer.lock`, `code-checker.php`
- Create: `tests/bootstrap.php`
- Create: `src/Exception.php`
- Create: `tests/Donut/Exception.basic.phpt`
- Modify: `.gitignore`

- [ ] **Step 1: Smazat starý obsah**

```bash
git rm -r -q src tests/Donut composer.lock code-checker.php
```

- [ ] **Step 2: Nahradit composer.json**

```json
{
	"name": "donut-org/donut",
	"type": "library",
	"description": "Runner workflow souborů nad unixovými příkazy",
	"license": "BSD-3-Clause",
	"authors": [
		{
			"name": "Jan Pecha",
			"homepage": "https://www.janpecha.cz/"
		}
	],
	"funding": [
		{"type": "other", "url": "https://www.janpecha.cz/donate/"}
	],
	"require": {
		"php": ">=8.1",
		"nette/utils": "^4.1.4"
	},
	"require-dev": {
		"nette/tester": "^2.6",
		"phpstan/phpstan": "^2.0"
	},
	"autoload": {
		"psr-4": {"Donut\\": "src/"}
	}
}
```

- [ ] **Step 3: Nainstalovat závislosti**

Run: `composer update`
Expected: vytvoří se nový `composer.lock`, `vendor/nette/utils` je verze 4.1.x.

Ověř: `composer show nette/utils | head -2` musí ukázat `4.1.` a nic staršího.

- [ ] **Step 4: Napsat bootstrap testů**

`tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());
```

- [ ] **Step 5: Rozšířit .gitignore**

`.gitignore` musí obsahovat (přidej, co chybí):

```
/vendor
/tests/tmp
/tests/**/output
composer.lock
```

Pozn.: `composer.lock` se u knihoven necommituje.

- [ ] **Step 6: Napsat výchozí výjimku**

`src/Exception.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;


/**
 * Základ všech výjimek balíčku, aby je volající mohl chytat jedním catch.
 */
class Exception extends \Exception
{
}
```

- [ ] **Step 7: Napsat test, který ověří, že se harness rozběhl**

`tests/Donut/Exception.basic.phpt`:

```php
<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::exception(
	fn() => throw new Donut\Exception('bum'),
	Donut\Exception::class,
	'bum'
);
```

- [ ] **Step 8: Spustit testy**

Run: `make test`
Expected: PASS, 1 test.

- [ ] **Step 9: Ověřit PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

Kdyby `phpstan.neon` odkazoval na neexistující cesty, oprav ho na:

```neon
parameters:
	level: max

	paths:
		- src
		- tests
```

Uprav i `Makefile`, cíl `phpstan` má volat `vendor/bin/phpstan`:

```make
phpstan:
		@vendor/bin/phpstan analyse
```

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "Vyprázdnit balíček pro runner workflow"
```

---

### Task 2: Template

Šablona je text s `{%KLIC%}`. Rozparsuje se jednou při načtení souboru a pak umí říct, které klíče čte, a dosadit hodnoty **jedním průchodem** — výsledek se dál nezpracovává.

Dvouznakové delimitery jsou zvolené tak, aby se nesrazily s procentem v datech: `{%` ani `%}` nevznikne percent-encodingem (byly by to `%7B` a `%7D`). Proto tu není žádný escape — samotné `%` prochází beze změny.

**Files:**
- Create: `src/Template.php`
- Create: `src/MissingKeyException.php`
- Test: `tests/Donut/Template.parse.phpt`
- Test: `tests/Donut/Template.render.phpt`

**Interfaces:**
- Consumes: `Donut\Exception` z Tasku 1.
- Produces:
  - `Donut\Template::parse(string $source): self`
  - `Donut\Template::getKeys(): array<int, string>` — unikátní, v pořadí prvního výskytu
  - `Donut\Template::render(array<string, string> $map): string` — hodí `MissingKeyException`
  - `Donut\Template::getSource(): string`
  - `Donut\Template::isKeyName(string $name): bool` — statická, používá ji i validátor na `out`/`set.key`/`foreach.as`
  - `Donut\MissingKeyException extends Donut\Exception`, metoda `getKey(): string`

- [ ] **Step 1: Napsat padající test na parsování**

`tests/Donut/Template.parse.phpt`:

```php
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
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/Template.parse.phpt`
Expected: FAIL, `Class 'Donut\Template' not found`

- [ ] **Step 3: Napsat MissingKeyException**

`src/MissingKeyException.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;


/**
 * Šablona četla klíč, který v mapě není. Podle specifikace je to tvrdá chyba.
 */
final class MissingKeyException extends Exception
{
	public function __construct(
		private readonly string $key,
	) {
		parent::__construct("Klíč '{$key}' v mapě neexistuje.");
	}


	public function getKey(): string
	{
		return $this->key;
	}
}
```

- [ ] **Step 4: Napsat Template**

`src/Template.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;


/**
 * Text s dosazovacími místy tvaru {%KLIC%}.
 *
 * Delimitery jsou dvouznakové, aby se nesrazily s procentem v datech: {% ani
 * %} nevznikne percent-encodingem, byly by to %7B a %7D. Samotné procento
 * proto nemá význam a žádný escape neexistuje — `date +%Y`, `printf '%d\n'`
 * i `?path=%2Ffoo` projdou beze změny.
 */
final class Template
{
	private const KeyPattern = '[A-Za-z0-9_]+';

	/** @param list<string|array{key: string}> $segments */
	private function __construct(
		private readonly string $source,
		private readonly array $segments,
	) {
	}


	public static function parse(string $source): self
	{
		$parts = \preg_split(
			'~(\{%' . self::KeyPattern . '%\})~',
			$source,
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		if ($parts === false) {
			throw new Exception("Šablonu '{$source}' se nepodařilo rozparsovat.");
		}

		$segments = [];

		foreach ($parts as $part) {
			if (\preg_match('~^\{%(' . self::KeyPattern . ')%\}$~D', $part, $m) === 1) {
				$segments[] = ['key' => $m[1]];

			} else {
				$segments[] = $part;
			}
		}

		return new self($source, $segments);
	}


	public static function isKeyName(string $name): bool
	{
		return \preg_match('~^' . self::KeyPattern . '$~D', $name) === 1;
	}


	/**
	 * Klíče, které šablona čte. Unikátní, v pořadí prvního výskytu.
	 *
	 * @return list<string>
	 */
	public function getKeys(): array
	{
		$keys = [];

		foreach ($this->segments as $segment) {
			if (\is_array($segment) && !\in_array($segment['key'], $keys, true)) {
				$keys[] = $segment['key'];
			}
		}

		return $keys;
	}


	/**
	 * Dosadí hodnoty jedním průchodem. Výsledek se dál nezpracovává, takže
	 * data obsahující {%NECO%} se nevyhodnocují.
	 *
	 * @param  array<string, string> $map
	 * @throws MissingKeyException
	 */
	public function render(array $map): string
	{
		$out = '';

		foreach ($this->segments as $segment) {
			if (\is_array($segment)) {
				$key = $segment['key'];

				if (!\array_key_exists($key, $map)) {
					throw new MissingKeyException($key);
				}

				$out .= $map[$key];

			} else {
				$out .= $segment;
			}
		}

		return $out;
	}


	public function getSource(): string
	{
		return $this->source;
	}
}
```

- [ ] **Step 5: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/Template.parse.phpt`
Expected: PASS

- [ ] **Step 6: Napsat test na dosazování**

`tests/Donut/Template.render.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\MissingKeyException;
use Donut\Template;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::same('abc', Template::parse('{%A%}')->render(['A' => 'abc']));

Assert::same(
	'task-1: Oprava (task-1)',
	Template::parse('{%BRANCH%}: {%TITLE%} ({%BRANCH%})')
		->render(['BRANCH' => 'task-1', 'TITLE' => 'Oprava'])
);

// prázdná hodnota je platná hodnota, dosadí se
Assert::same('x=', Template::parse('x={%A%}')->render(['A' => '']));

// jeden průchod: {%B%} v datech se nevyhodnotí
Assert::same('{%B%}', Template::parse('{%A%}')->render(['A' => '{%B%}', 'B' => 'ne']));

// co není šablona, projde beze změny — bez jakéhokoliv escapování
Assert::same('100% hotovo', Template::parse('100% hotovo')->render([]));
Assert::same('%2F%3A', Template::parse('%2F%3A')->render([]));
Assert::same('?q=%20%', Template::parse('?q=%20%')->render([]));
Assert::same('date +%Y', Template::parse('date +%Y')->render([]));
Assert::same("printf '%d\\n'", Template::parse("printf '%d\\n'")->render([]));

// reálné případy z přepisu
Assert::same(
	'https://api.trello.com/1/cards/abc?list=true',
	Template::parse('https://api.trello.com/1/cards/{%SHORT_ID%}?list=true')
		->render(['SHORT_ID' => 'abc'])
);
Assert::same(
	'{"idList": "5f2"}',
	Template::parse('{"idList": "{%TARGET_LIST_ID%}"}')->render(['TARGET_LIST_ID' => '5f2'])
);

// chybějící klíč je tvrdá chyba
Assert::exception(
	fn() => Template::parse('{%A%}')->render([]),
	MissingKeyException::class,
	"Klíč 'A' v mapě neexistuje."
);

$e = Assert::exception(
	fn() => Template::parse('{%NECO%}')->render([]),
	MissingKeyException::class
);
Assert::same('NECO', $e->getKey());
```

- [ ] **Step 7: Spustit testy**

Run: `make test`
Expected: PASS, 3 testy.

- [ ] **Step 8: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 9: Commit**

```bash
git add src/Template.php src/MissingKeyException.php tests/Donut/Template.parse.phpt tests/Donut/Template.render.phpt
git commit -m "Template: parsování a dosazování {%KLIC%}"
```

---

### Task 3: Kámen a jeho parser

**Files:**
- Create: `src/Format/Input.php`
- Create: `src/Format/StdinSpec.php`
- Create: `src/Format/Block.php`
- Create: `src/Parser/ParseException.php`
- Create: `src/Parser/JsonSource.php`
- Create: `src/Parser/BlockParser.php`
- Test: `tests/Donut/BlockParser.valid.phpt`
- Test: `tests/Donut/BlockParser.invalid.phpt`

**Interfaces:**
- Consumes: `Donut\Template`, `Donut\Exception`.
- Produces:
  - `Donut\Format\Input` — veřejné readonly `string $name`, `bool $required`, `?string $default`, `?string $description`
  - `Donut\Format\StdinSpec` — `bool $required`, `?string $description`
  - `Donut\Format\Block` — `string $name`, `?string $description`, `string $command`, `array<int, array<int, Template>> $args`, `array<string, Input> $inputs`, `?StdinSpec $stdin`, `?int $timeout`, `bool|array<int, int> $allowFailure`
  - `Donut\Parser\JsonSource::readFile(string $path): array<mixed>` — statická
  - `Donut\Parser\JsonSource::parseInputs(array<mixed> $data, string $location): array<string, Input>` — statická, čte klíč `inputs`
  - `Donut\Parser\JsonSource::parseAllowFailure(mixed $value, string $location, string $what): bool|array<int, int>` — statická
  - `Donut\Parser\JsonSource::optionalString(array<mixed> $data, string $key, string $location, string $what): ?string` — statická
  - `Donut\Parser\BlockParser::parseFile(string $path): Block`
  - `Donut\Parser\BlockParser::parseArray(array<mixed> $data, string $location): Block`
  - `Donut\Parser\ParseException extends Donut\Exception`

**Proč `JsonSource`:** kámen a workflow sdílí tři věci — čtení souboru, deklaraci `inputs` a `allow_failure`. Bez společného místa by je Task 4 opsal a formát by se pak měnil na dvou místech. Kontrola `name` proti názvu souboru sdílená **není**, každý parser má vlastní hlášku.

- [ ] **Step 1: Napsat padající test na platný kámen**

`tests/Donut/BlockParser.valid.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Parser\BlockParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$block = (new BlockParser)->parseArray([
	'name' => 'curl-get',
	'description' => 'HTTP GET.',
	'command' => 'curl',
	'args' => [
		['-sS', '--fail'],
		['--config', '{%CURLRC%}'],
		['{%URL%}'],
	],
	'inputs' => [
		'URL' => ['required' => true, 'description' => 'Adresa'],
		'CURLRC' => ['required' => false],
	],
], 'curl-get.json');

Assert::same('curl-get', $block->name);
Assert::same('HTTP GET.', $block->description);
Assert::same('curl', $block->command);
Assert::count(3, $block->args);
Assert::same('{%URL%}', $block->args[2][0]->getSource());
Assert::same(['URL'], $block->args[2][0]->getKeys());

Assert::same(['URL', 'CURLRC'], array_keys($block->inputs));
Assert::true($block->inputs['URL']->required);
Assert::same('Adresa', $block->inputs['URL']->description);
Assert::false($block->inputs['CURLRC']->required);
Assert::null($block->inputs['CURLRC']->default);

Assert::null($block->stdin);
Assert::null($block->timeout);
Assert::false($block->allowFailure);

// defaulty: required je true, když se neuvede
$block = (new BlockParser)->parseArray([
	'name' => 'jq',
	'command' => 'jq',
	'args' => [['{%FILTER%}']],
	'inputs' => ['FILTER' => []],
	'stdin' => ['required' => true],
	'timeout' => 30,
	'allow_failure' => [0, 1],
], 'jq.json');

Assert::null($block->description);
Assert::true($block->inputs['FILTER']->required);
Assert::notNull($block->stdin);
Assert::true($block->stdin->required);
Assert::same(30, $block->timeout);
Assert::same([0, 1], $block->allowFailure);

// allow_failure: true
$block = (new BlockParser)->parseArray([
	'name' => 'x',
	'command' => 'x',
	'args' => [],
	'allow_failure' => true,
], 'x.json');

Assert::true($block->allowFailure);
Assert::same([], $block->inputs);
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/BlockParser.valid.phpt`
Expected: FAIL, `Class 'Donut\Parser\BlockParser' not found`

- [ ] **Step 3: Napsat hodnotové objekty**

`src/Format/Input.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Deklarace jedné proměnné dosazované do args kamene.
 */
final class Input
{
	public function __construct(
		public readonly string $name,
		public readonly bool $required = true,
		public readonly ?string $default = null,
		public readonly ?string $description = null,
	) {
	}
}
```

`src/Format/StdinSpec.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Přítomnost tohoto objektu znamená, že kámen čte standardní vstup.
 */
final class StdinSpec
{
	public function __construct(
		public readonly bool $required = true,
		public readonly ?string $description = null,
	) {
	}
}
```

`src/Format/Block.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


/**
 * Parametrizovaná funkce nad jedním příkazem. Neví nic o workflow, které ji
 * volá, ani o klíčích v mapě enginu.
 */
final class Block
{
	/**
	 * @param array<int, array<int, Template>> $args   skupiny argumentů
	 * @param array<string, Input>             $inputs klíčem je jméno vstupu
	 * @param bool|array<int, int>             $allowFailure
	 *        false = jen 0, true = cokoliv, pole = výčet povolených exit kódů
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $command,
		public readonly array $args,
		public readonly array $inputs = [],
		public readonly ?StdinSpec $stdin = null,
		public readonly ?int $timeout = null,
		public readonly bool|array $allowFailure = false,
		public readonly ?string $description = null,
	) {
	}
}
```

- [ ] **Step 4: Napsat ParseException a JsonSource**

`src/Parser/ParseException.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Exception;


/**
 * Soubor nejde načíst nebo neodpovídá struktuře formátu.
 */
final class ParseException extends Exception
{
}
```

`src/Parser/JsonSource.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Input;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Části parsování společné kamenům i workflow.
 *
 * Oba formáty se čtou stejně a oba deklarují inputs; kámen a krok navíc
 * sdílejí tvar allow_failure. Bez tohohle místa by se to opisovalo
 * a měnilo dvakrát.
 */
final class JsonSource
{
	/**
	 * @return array<mixed>
	 * @throws ParseException
	 */
	public static function readFile(string $path): array
	{
		$content = @\file_get_contents($path);

		if ($content === false) {
			throw new ParseException("Soubor '{$path}' nejde přečíst.");
		}

		try {
			$data = Json::decode($content, forceArrays: true);

		} catch (JsonException $e) {
			throw new ParseException("Soubor '{$path}' není platný JSON: {$e->getMessage()}", 0, $e);
		}

		if (!\is_array($data)) {
			throw new ParseException("Soubor '{$path}' musí obsahovat objekt.");
		}

		return $data;
	}


	/**
	 * Přečte klíč `inputs`. Chybějící klíč znamená prázdnou deklaraci.
	 *
	 * @param  array<mixed> $data celý objekt kamene nebo workflow
	 * @return array<string, Input>
	 * @throws ParseException
	 */
	public static function parseInputs(array $data, string $location): array
	{
		if (!isset($data['inputs'])) {
			return [];
		}

		if (!\is_array($data['inputs'])) {
			throw new ParseException("{$location}: klíč 'inputs' musí být objekt.");
		}

		$inputs = [];

		foreach ($data['inputs'] as $name => $spec) {
			if (!\is_string($name)) {
				throw new ParseException("{$location}: jména vstupů musí být řetězce.");
			}

			if (!\is_array($spec)) {
				throw new ParseException("{$location}: vstup '{$name}' musí být objekt.");
			}

			$inputs[$name] = new Input(
				name: $name,
				required: isset($spec['required']) ? (bool) $spec['required'] : true,
				default: self::optionalString($spec, 'default', $location, "default vstupu '{$name}'"),
				description: self::optionalString($spec, 'description', $location, "description vstupu '{$name}'"),
			);
		}

		return $inputs;
	}


	/**
	 * Nepovinná textová hodnota. Chybí -> null. Skalár -> text (číslo v JSON
	 * je tedy platný `default`). Pole nebo objekt -> chyba, protože v mapě
	 * enginu jsou jen texty.
	 *
	 * Tiché zahození by nejvíc bolelo u `default`, který se za běhu dosazuje:
	 * rozbitý default by se z „žádný default" projevil až chybějícím
	 * argumentem někde úplně jinde.
	 *
	 * @param  array<mixed> $data
	 * @param  string $what jak se na hodnotu odkázat v hlášce
	 * @throws ParseException
	 */
	public static function optionalString(array $data, string $key, string $location, string $what): ?string
	{
		if (!isset($data[$key])) {
			return null;
		}

		if (!\is_scalar($data[$key])) {
			throw new ParseException("{$location}: {$what} musí být řetězec.");
		}

		return (string) $data[$key];
	}


	/**
	 * @param  string $what jak se na pole odkázat v hlášce (`allow_failure`,
	 *                      nebo `steps[0].allow_failure` u kroku)
	 * @return bool|array<int, int>
	 * @throws ParseException
	 */
	public static function parseAllowFailure(mixed $value, string $location, string $what): bool|array
	{
		if (\is_bool($value)) {
			return $value;
		}

		if (\is_array($value)) {
			$codes = [];

			foreach ($value as $code) {
				if (!\is_int($code)) {
					throw new ParseException(
						"{$location}: {$what} jako pole musí obsahovat jen celá čísla."
					);
				}

				$codes[] = $code;
			}

			return $codes;
		}

		throw new ParseException(
			"{$location}: {$what} musí být true, false, nebo pole celých čísel."
		);
	}
}
```

- [ ] **Step 4b: Napsat BlockParser**

`src/Parser/BlockParser.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Block;
use Donut\Format\StdinSpec;
use Donut\Template;


/**
 * JSON souboru z blocks/ na objekt Block.
 *
 * Kontroluje jen strukturu jednoho souboru. Vazby mezi soubory řeší validátor.
 */
final class BlockParser
{
	/**
	 * @throws ParseException
	 */
	public function parseFile(string $path): Block
	{
		$block = $this->parseArray(JsonSource::readFile($path), $path);
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new ParseException(
				"{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		return $block;
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	public function parseArray(array $data, string $location): Block
	{
		$known = ['name', 'description', 'command', 'args', 'inputs', 'stdin', 'timeout', 'allow_failure'];

		foreach (\array_keys($data) as $key) {
			if (!\in_array($key, $known, true)) {
				throw new ParseException("{$location}: neznámý klíč '{$key}'.");
			}
		}

		$name = $this->requireString($data, 'name', $location);
		$command = $this->requireString($data, 'command', $location);

		if (!isset($data['args']) || !\is_array($data['args'])) {
			throw new ParseException("{$location}: klíč 'args' je povinný a musí být pole.");
		}

		$args = [];

		foreach ($data['args'] as $i => $group) {
			if (!\is_array($group)) {
				throw new ParseException("{$location}: args[{$i}] musí být pole řetězců.");
			}

			$parsedGroup = [];

			foreach ($group as $j => $element) {
				if (!\is_string($element)) {
					throw new ParseException("{$location}: args[{$i}][{$j}] musí být řetězec.");
				}

				$parsedGroup[] = Template::parse($element);
			}

			$args[] = $parsedGroup;
		}

		$stdin = null;

		if (isset($data['stdin'])) {
			if (!\is_array($data['stdin'])) {
				throw new ParseException("{$location}: klíč 'stdin' musí být objekt.");
			}

			$stdin = new StdinSpec(
				required: isset($data['stdin']['required']) ? (bool) $data['stdin']['required'] : true,
				description: JsonSource::optionalString($data['stdin'], 'description', $location, 'stdin.description'),
			);
		}

		$timeout = null;

		if (isset($data['timeout'])) {
			if (!\is_int($data['timeout']) || $data['timeout'] < 0) {
				throw new ParseException("{$location}: 'timeout' musí být nezáporné celé číslo.");
			}

			$timeout = $data['timeout'];
		}

		return new Block(
			name: $name,
			command: $command,
			args: $args,
			inputs: JsonSource::parseInputs($data, $location),
			stdin: $stdin,
			timeout: $timeout,
			allowFailure: isset($data['allow_failure'])
				? JsonSource::parseAllowFailure($data['allow_failure'], $location, 'allow_failure')
				: false,
			description: JsonSource::optionalString($data, 'description', $location, 'description'),
		);
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	private function requireString(array $data, string $key, string $location): string
	{
		if (!isset($data[$key]) || !\is_string($data[$key]) || $data[$key] === '') {
			throw new ParseException("{$location}: klíč '{$key}' je povinný a musí být neprázdný řetězec.");
		}

		return $data[$key];
	}
}
```

- [ ] **Step 5: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/BlockParser.valid.phpt`
Expected: PASS

- [ ] **Step 6: Napsat test na vadné kameny**

`tests/Donut/BlockParser.invalid.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Parser\BlockParser;
use Donut\Parser\ParseException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parser = new BlockParser;

$assertFails = function (array $data, string $message) use ($parser): void {
	Assert::exception(
		fn() => $parser->parseArray($data, 'x.json'),
		ParseException::class,
		$message
	);
};

$assertFails(
	['command' => 'x', 'args' => []],
	"x.json: klíč 'name' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'x', 'args' => []],
	"x.json: klíč 'command' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'x', 'command' => 'x'],
	"x.json: klíč 'args' je povinný a musí být pole."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => ['-v']],
	'x.json: args[0] musí být pole řetězců.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [[1]]],
	'x.json: args[0][0] musí být řetězec.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => 'ano'],
	'x.json: allow_failure musí být true, false, nebo pole celých čísel.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'allow_failure' => ['a']],
	'x.json: allow_failure jako pole musí obsahovat jen celá čísla.'
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'timeout' => -1],
	"x.json: 'timeout' musí být nezáporné celé číslo."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'outputs' => []],
	"x.json: neznámý klíč 'outputs'."
);

$assertFails(
	['name' => 'x', 'command' => 'x', 'args' => [], 'inputs' => ['A' => 'ne']],
	"x.json: vstup 'A' musí být objekt."
);
```

Pozn.: `'outputs'` je v testu schválně — chytá překlep i to, že klíč `output`
už formát nezná.

- [ ] **Step 7: Spustit testy**

Run: `make test`
Expected: PASS, 5 testů.

- [ ] **Step 8: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 9: Commit**

```bash
git add src/Format src/Parser tests/Donut/BlockParser.valid.phpt tests/Donut/BlockParser.invalid.phpt
git commit -m "BlockParser a JsonSource: načítání kamenů z JSON"
```

---

### Task 4: Workflow, kroky a jejich parser

**Files:**
- Create: `src/Format/Step.php`
- Create: `src/Format/RunStep.php`
- Create: `src/Format/IfStep.php`
- Create: `src/Format/SetStep.php`
- Create: `src/Format/ForeachStep.php`
- Create: `src/Format/Condition.php`
- Create: `src/Format/Workflow.php`
- Create: `src/Parser/WorkflowParser.php`
- Test: `tests/Donut/WorkflowParser.valid.phpt`
- Test: `tests/Donut/WorkflowParser.invalid.phpt`

**Interfaces:**
- Consumes: `Donut\Template`, `Donut\Format\Input`, `Donut\Parser\ParseException`, a z Tasku 3 `Donut\Parser\JsonSource` — `readFile()`, `parseInputs()` a `parseAllowFailure()` se **neopisují**, volají se.
- Produces:
  - `Donut\Format\Step` — prázdné rozhraní, společný typ pro pole kroků; všechny kroky mají `?string $name`
  - `Donut\Format\RunStep` — `string $block`, `array<string, Template> $in`, `array<string, string> $out` (klíč = kanál `result`/`stderr`/`exit_code`), `?int $timeout`, `bool|array<int, int>|null $allowFailure`, `?string $name`
  - `Donut\Format\IfStep` — `Condition $condition`, `array<int, Step> $then`, `array<int, Step> $else`, `?string $name`
  - `Donut\Format\SetStep` — `string $key`, `Template $value`, `?string $name`
  - `Donut\Format\ForeachStep` — `Template $over`, `string $as`, `array<int, Step> $steps`, `?string $name`
  - `Donut\Format\Condition` — `Template $left`, `string $op`, `?Template $right`; konstanta `Condition::Operators` je `array<int, string>`
  - `Donut\Format\Workflow` — `string $name`, `?string $description`, `array<string, Input> $inputs`, `array<int, Step> $steps`
  - `Donut\Parser\WorkflowParser::parseFile(string $path): Workflow`
  - `Donut\Parser\WorkflowParser::parseArray(array<mixed> $data, string $location): Workflow`

- [ ] **Step 1: Napsat padající test na platné workflow**

`tests/Donut/WorkflowParser.valid.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Parser\WorkflowParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$wf = (new WorkflowParser)->parseArray([
	'name' => 'demo',
	'description' => 'Ukázka.',
	'inputs' => [
		'ENV' => ['required' => true],
		'TAG' => ['required' => false, 'default' => 'latest'],
	],
	'steps' => [
		[
			'type' => 'run',
			'name' => 'stáhnout',
			'block' => 'curl-get',
			'in' => ['URL' => 'https://x/{%ENV%}'],
			'out' => ['result' => 'BODY', 'exit_code' => 'RC'],
			'timeout' => 5,
			'allow_failure' => [0, 1],
		],
		[
			'type' => 'if',
			'condition' => ['left' => '{%RC%}', 'op' => 'eq', 'right' => '0'],
			'then' => [
				['type' => 'set', 'key' => 'OK', 'value' => 'ano'],
			],
			'else' => [
				['type' => 'set', 'key' => 'OK', 'value' => 'ne'],
			],
		],
		[
			'type' => 'foreach',
			'over' => '{%BODY%}',
			'as' => 'LINE',
			'steps' => [
				['type' => 'set', 'key' => 'LAST', 'value' => '{%LINE%}'],
			],
		],
	],
], 'demo.json');

Assert::same('demo', $wf->name);
Assert::same('Ukázka.', $wf->description);
Assert::same(['ENV', 'TAG'], array_keys($wf->inputs));
Assert::same('latest', $wf->inputs['TAG']->default);
Assert::count(3, $wf->steps);

$run = $wf->steps[0];
Assert::type(RunStep::class, $run);
Assert::same('stáhnout', $run->name);
Assert::same('curl-get', $run->block);
Assert::same(['URL'], array_keys($run->in));
Assert::same(['ENV'], $run->in['URL']->getKeys());
Assert::same(['result' => 'BODY', 'exit_code' => 'RC'], $run->out);
Assert::same(5, $run->timeout);
Assert::same([0, 1], $run->allowFailure);

$if = $wf->steps[1];
Assert::type(IfStep::class, $if);
Assert::same('eq', $if->condition->op);
Assert::same(['RC'], $if->condition->left->getKeys());
Assert::same('0', $if->condition->right?->getSource());
Assert::count(1, $if->then);
Assert::count(1, $if->else);
Assert::type(SetStep::class, $if->then[0]);
Assert::same('OK', $if->then[0]->key);

$each = $wf->steps[2];
Assert::type(ForeachStep::class, $each);
Assert::same(['BODY'], $each->over->getKeys());
Assert::same('LINE', $each->as);
Assert::count(1, $each->steps);

// minimální run krok: bez in, out, name
$wf = (new WorkflowParser)->parseArray([
	'name' => 'min',
	'steps' => [['type' => 'run', 'block' => 'x']],
], 'min.json');

Assert::same([], $wf->inputs);
Assert::same([], $wf->steps[0]->in);
Assert::same([], $wf->steps[0]->out);
Assert::null($wf->steps[0]->name);
Assert::null($wf->steps[0]->allowFailure);

// empty / not_empty nemusí mít right
$wf = (new WorkflowParser)->parseArray([
	'name' => 'e',
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%A%}', 'op' => 'not_empty'],
		'then' => [],
	]],
], 'e.json');

Assert::null($wf->steps[0]->condition->right);
Assert::same([], $wf->steps[0]->then);
Assert::same([], $wf->steps[0]->else);
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/WorkflowParser.valid.phpt`
Expected: FAIL, `Class 'Donut\Parser\WorkflowParser' not found`

- [ ] **Step 3: Napsat kroky a podmínku**

`src/Format/Step.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Společný typ pro položky pole steps.
 */
interface Step
{
	public function getName(): ?string;
}
```

`src/Format/Condition.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class Condition
{
	/** Operátory podle sekce 2 specifikace. */
	public const Operators = [
		'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'not_empty',
	];

	/** Operátory, které klíč 'right' ignorují. */
	public const UnaryOperators = ['empty', 'not_empty'];

	public function __construct(
		public readonly Template $left,
		public readonly string $op,
		public readonly ?Template $right = null,
	) {
	}
}
```

`src/Format/RunStep.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class RunStep implements Step
{
	/** Kanály, které smí stát v out. */
	public const Channels = ['result', 'stderr', 'exit_code'];

	/**
	 * @param array<string, Template> $in  vstup kamene => šablona; klíč STDIN plní standardní vstup
	 * @param array<string, string>   $out kanál => klíč v mapě enginu
	 * @param bool|array<int, int>|null $allowFailure null = převzít z kamene
	 */
	public function __construct(
		public readonly string $block,
		public readonly array $in = [],
		public readonly array $out = [],
		public readonly ?int $timeout = null,
		public readonly bool|array|null $allowFailure = null,
		public readonly ?string $name = null,
	) {
	}


	public function getName(): ?string
	{
		return $this->name;
	}
}
```

`src/Format/IfStep.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;


final class IfStep implements Step
{
	/**
	 * @param array<int, Step> $then
	 * @param array<int, Step> $else
	 */
	public function __construct(
		public readonly Condition $condition,
		public readonly array $then = [],
		public readonly array $else = [],
		public readonly ?string $name = null,
	) {
	}


	public function getName(): ?string
	{
		return $this->name;
	}
}
```

`src/Format/SetStep.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class SetStep implements Step
{
	public function __construct(
		public readonly string $key,
		public readonly Template $value,
		public readonly ?string $name = null,
	) {
	}


	public function getName(): ?string
	{
		return $this->name;
	}
}
```

`src/Format/ForeachStep.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class ForeachStep implements Step
{
	/** @param array<int, Step> $steps */
	public function __construct(
		public readonly Template $over,
		public readonly string $as,
		public readonly array $steps = [],
		public readonly ?string $name = null,
	) {
	}


	public function getName(): ?string
	{
		return $this->name;
	}
}
```

`src/Format/Workflow.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Format;


final class Workflow
{
	/**
	 * @param array<string, Input> $inputs
	 * @param array<int, Step>     $steps
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $inputs = [],
		public readonly array $steps = [],
		public readonly ?string $description = null,
	) {
	}
}
```

- [ ] **Step 4: Napsat WorkflowParser**

`src/Parser/WorkflowParser.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * JSON souboru z workflows/ na objekt Workflow.
 *
 * Kontroluje jen strukturu jednoho souboru — že kroky mají povinné klíče
 * správných typů. Existenci kamenů a tok klíčů řeší validátor.
 */
final class WorkflowParser
{
	/**
	 * @throws ParseException
	 */
	public function parseFile(string $path): Workflow
	{
		$workflow = $this->parseArray(JsonSource::readFile($path), $path);
		$expected = \basename($path, '.json');

		if ($workflow->name !== $expected) {
			throw new ParseException(
				"{$path}: name '{$workflow->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		return $workflow;
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	public function parseArray(array $data, string $location): Workflow
	{
		foreach (\array_keys($data) as $key) {
			if (!\in_array($key, ['name', 'description', 'inputs', 'steps'], true)) {
				throw new ParseException("{$location}: neznámý klíč '{$key}'.");
			}
		}

		if (!isset($data['name']) || !\is_string($data['name']) || $data['name'] === '') {
			throw new ParseException("{$location}: klíč 'name' je povinný a musí být neprázdný řetězec.");
		}

		if (!isset($data['steps']) || !\is_array($data['steps'])) {
			throw new ParseException("{$location}: klíč 'steps' je povinný a musí být pole.");
		}

		return new Workflow(
			name: $data['name'],
			inputs: JsonSource::parseInputs($data, $location),
			steps: $this->parseSteps($data['steps'], $location, 'steps'),
			description: JsonSource::optionalString($data, 'description', $location, 'description'),
		);
	}


	/**
	 * @param  array<mixed> $steps
	 * @return array<int, Step>
	 * @throws ParseException
	 */
	private function parseSteps(array $steps, string $location, string $path): array
	{
		$result = [];

		foreach ($steps as $i => $step) {
			if (!\is_array($step)) {
				throw new ParseException("{$location}: {$path}[{$i}] musí být objekt.");
			}

			$result[] = $this->parseStep($step, $location, "{$path}[{$i}]");
		}

		return $result;
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseStep(array $step, string $location, string $path): Step
	{
		$type = $step['type'] ?? null;

		if (!\is_string($type)) {
			throw new ParseException("{$location}: {$path} nemá klíč 'type'.");
		}

		$name = JsonSource::optionalString($step, 'name', $location, "{$path}.name");

		return match ($type) {
			'run' => $this->parseRun($step, $location, $path, $name),
			'if' => $this->parseIf($step, $location, $path, $name),
			'set' => $this->parseSet($step, $location, $path, $name),
			'foreach' => $this->parseForeach($step, $location, $path, $name),
			default => throw new ParseException("{$location}: {$path} má neznámý typ kroku '{$type}'."),
		};
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseRun(array $step, string $location, string $path, ?string $name): RunStep
	{
		if (!isset($step['block']) || !\is_string($step['block'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'block'.");
		}

		$in = [];

		foreach ($this->objectOrEmpty($step, 'in', $location, $path) as $key => $value) {
			if (!\is_string($key) || !\is_string($value)) {
				throw new ParseException("{$location}: {$path}.in musí být objekt řetězec => řetězec.");
			}

			$in[$key] = Template::parse($value);
		}

		$out = [];

		foreach ($this->objectOrEmpty($step, 'out', $location, $path) as $channel => $key) {
			if (!\is_string($channel) || !\is_string($key)) {
				throw new ParseException("{$location}: {$path}.out musí být objekt řetězec => řetězec.");
			}

			$out[$channel] = $key;
		}

		$allowFailure = isset($step['allow_failure'])
			? JsonSource::parseAllowFailure($step['allow_failure'], $location, "{$path}.allow_failure")
			: null;

		$timeout = null;

		if (isset($step['timeout'])) {
			if (!\is_int($step['timeout']) || $step['timeout'] < 0) {
				throw new ParseException("{$location}: {$path}.timeout musí být nezáporné celé číslo.");
			}

			$timeout = $step['timeout'];
		}

		return new RunStep(
			block: $step['block'],
			in: $in,
			out: $out,
			timeout: $timeout,
			allowFailure: $allowFailure,
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseIf(array $step, string $location, string $path, ?string $name): IfStep
	{
		if (!isset($step['condition']) || !\is_array($step['condition'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'condition'.");
		}

		$condition = $step['condition'];

		if (!isset($condition['left']) || !\is_string($condition['left'])) {
			throw new ParseException("{$location}: {$path}.condition nemá 'left'.");
		}

		if (!isset($condition['op']) || !\is_string($condition['op'])) {
			throw new ParseException("{$location}: {$path}.condition nemá 'op'.");
		}

		if (!isset($step['then']) || !\is_array($step['then'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'then'.");
		}

		if (isset($step['else']) && !\is_array($step['else'])) {
			throw new ParseException("{$location}: {$path}.else musí být pole.");
		}

		$right = null;

		if (isset($condition['right'])) {
			if (!\is_string($condition['right'])) {
				throw new ParseException("{$location}: {$path}.condition.right musí být řetězec.");
			}

			$right = Template::parse($condition['right']);
		}

		return new IfStep(
			condition: new Condition(
				left: Template::parse($condition['left']),
				op: $condition['op'],
				right: $right,
			),
			then: $this->parseSteps($step['then'], $location, "{$path}.then"),
			else: isset($step['else'])
				? $this->parseSteps($step['else'], $location, "{$path}.else")
				: [],
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseSet(array $step, string $location, string $path, ?string $name): SetStep
	{
		if (!isset($step['key']) || !\is_string($step['key'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'key'.");
		}

		if (!isset($step['value']) || !\is_string($step['value'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'value'.");
		}

		return new SetStep(
			key: $step['key'],
			value: Template::parse($step['value']),
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseForeach(array $step, string $location, string $path, ?string $name): ForeachStep
	{
		if (!isset($step['over']) || !\is_string($step['over'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'over'.");
		}

		if (!isset($step['as']) || !\is_string($step['as'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'as'.");
		}

		if (!isset($step['steps']) || !\is_array($step['steps'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'steps'.");
		}

		return new ForeachStep(
			over: Template::parse($step['over']),
			as: $step['as'],
			steps: $this->parseSteps($step['steps'], $location, "{$path}.steps"),
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @return array<mixed>
	 * @throws ParseException
	 */
	private function objectOrEmpty(array $step, string $key, string $location, string $path): array
	{
		if (!isset($step[$key])) {
			return [];
		}

		if (!\is_array($step[$key])) {
			throw new ParseException("{$location}: {$path}.{$key} musí být objekt.");
		}

		return $step[$key];
	}
}
```

- [ ] **Step 5: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/WorkflowParser.valid.phpt`
Expected: PASS

- [ ] **Step 6: Napsat test na vadná workflow**

`tests/Donut/WorkflowParser.invalid.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Parser\ParseException;
use Donut\Parser\WorkflowParser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parser = new WorkflowParser;

$assertFails = function (array $data, string $message) use ($parser): void {
	Assert::exception(
		fn() => $parser->parseArray($data, 'w.json'),
		ParseException::class,
		$message
	);
};

$assertFails(
	['steps' => []],
	"w.json: klíč 'name' je povinný a musí být neprázdný řetězec."
);

$assertFails(
	['name' => 'w'],
	"w.json: klíč 'steps' je povinný a musí být pole."
);

$assertFails(
	['name' => 'w', 'steps' => [[]]],
	"w.json: steps[0] nemá klíč 'type'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'while']]],
	"w.json: steps[0] má neznámý typ kroku 'while'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'run']]],
	"w.json: steps[0] nemá klíč 'block'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'set', 'key' => 'A']]],
	"w.json: steps[0] nemá klíč 'value'."
);

$assertFails(
	['name' => 'w', 'steps' => [['type' => 'foreach', 'over' => '{%A%}', 'as' => 'B']]],
	"w.json: steps[0] nemá klíč 'steps'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [['type' => 'if', 'condition' => ['op' => 'eq'], 'then' => []]],
	],
	"w.json: steps[0].condition nemá 'left'."
);

// chyba ve vnořeném kroku ukazuje na správné místo
$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => '1'],
			'then' => [['type' => 'run']],
		]],
	],
	"w.json: steps[0].then[0] nemá klíč 'block'."
);

$assertFails(
	[
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%A%}',
			'as' => 'B',
			'steps' => [['type' => 'set', 'key' => 'C']],
		]],
	],
	"w.json: steps[0].steps[0] nemá klíč 'value'."
);
```

- [ ] **Step 7: Spustit testy**

Run: `make test`
Expected: PASS, 7 testů.

- [ ] **Step 8: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 9: Commit**

```bash
git add src/Format src/Parser tests/Donut/WorkflowParser.valid.phpt tests/Donut/WorkflowParser.invalid.phpt
git commit -m "WorkflowParser: načítání workflow a kroků z JSON"
```

---

### Task 5: BlockRepository

Validátor potřebuje kameny hledat podle jména. Načítání z adresáře je jediná zodpovědnost tohohle objektu.

**Files:**
- Create: `src/BlockRepository.php`
- Test: `tests/Donut/BlockRepository.phpt`

**Interfaces:**
- Consumes: `Donut\Parser\BlockParser`, `Donut\Format\Block`, `Donut\Parser\ParseException`.
- Produces:
  - `Donut\BlockRepository::__construct(string $directory, ?BlockParser $parser = null)`
  - `Donut\BlockRepository::has(string $name): bool`
  - `Donut\BlockRepository::get(string $name): Block` — hodí `ParseException`, když kámen není
  - `Donut\BlockRepository::getNames(): array<int, string>` — abecedně

- [ ] **Step 1: Napsat padající test**

`tests/Donut/BlockRepository.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\ParseException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo',
	'command' => 'echo',
	'args' => [['{%TEXT%}']],
	'inputs' => ['TEXT' => ['required' => true]],
]));

file_put_contents($dir . '/cat.json', json_encode([
	'name' => 'cat',
	'command' => 'cat',
	'args' => [],
]));

$repo = new BlockRepository($dir);

Assert::same(['cat', 'echo'], $repo->getNames());
Assert::true($repo->has('echo'));
Assert::false($repo->has('nope'));
Assert::same('echo', $repo->get('echo')->name);
Assert::same('cat', $repo->get('cat')->command);

// stejná instance při opakovaném volání (načítá se jednou)
Assert::same($repo->get('echo'), $repo->get('echo'));

Assert::exception(
	fn() => $repo->get('nope'),
	ParseException::class,
	"Kámen 'nope' neexistuje."
);

// neexistující adresář
Assert::exception(
	fn() => new BlockRepository($dir . '/chybi'),
	ParseException::class
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/BlockRepository.phpt`
Expected: FAIL, `Class 'Donut\BlockRepository' not found`

- [ ] **Step 3: Napsat BlockRepository**

`src/BlockRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;

use Donut\Format\Block;
use Donut\Parser\BlockParser;
use Donut\Parser\ParseException;


/**
 * Kameny z adresáře blocks/, hledané podle jména.
 *
 * Soubory se parsují líně, ale seznam jmen zná hned — kvůli --list
 * a kvůli hlášce „kámen neexistuje" ve validátoru.
 */
final class BlockRepository
{
	private readonly BlockParser $parser;

	/** @var array<string, string> jméno => cesta k souboru */
	private array $files = [];

	/** @var array<string, Block> */
	private array $loaded = [];


	/**
	 * @throws ParseException
	 */
	public function __construct(
		private readonly string $directory,
		?BlockParser $parser = null,
	) {
		$this->parser = $parser ?? new BlockParser;

		if (!\is_dir($directory)) {
			throw new ParseException("Adresář s kameny '{$directory}' neexistuje.");
		}

		$paths = \glob($directory . '/*.json');

		foreach ($paths === false ? [] : $paths as $path) {
			$this->files[\basename($path, '.json')] = $path;
		}

		\ksort($this->files);
	}


	public function has(string $name): bool
	{
		return isset($this->files[$name]);
	}


	/**
	 * @throws ParseException
	 */
	public function get(string $name): Block
	{
		if (!isset($this->files[$name])) {
			throw new ParseException("Kámen '{$name}' neexistuje.");
		}

		return $this->loaded[$name] ??= $this->parser->parseFile($this->files[$name]);
	}


	/** @return array<int, string> */
	public function getNames(): array
	{
		return \array_keys($this->files);
	}


	public function getDirectory(): string
	{
		return $this->directory;
	}
}
```

- [ ] **Step 4: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/BlockRepository.phpt`
Expected: PASS

- [ ] **Step 5: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 6: Commit**

```bash
git add src/BlockRepository.php tests/Donut/BlockRepository.phpt
git commit -m "BlockRepository: kameny z adresáře podle jména"
```

---

### Task 6: Validátor — kontroly zapojení kroků

První polovina sekce 5 specifikace: všechno, co jde zjistit z jednoho kroku a jeho kamene, bez sledování toku klíčů.

**Files:**
- Create: `src/Validator/Problem.php`
- Create: `src/Validator/Result.php`
- Create: `src/Validator/Validator.php`
- Test: `tests/Donut/Validator.steps.phpt`

**Interfaces:**
- Consumes: `Donut\BlockRepository`, `Donut\Format\*`, `Donut\Template`.
- Produces:
  - `Donut\Validator\Problem` — `string $severity` (`Problem::Error` nebo `Problem::Warning`), `string $message`, `string $location`; metoda `__toString(): string` vrací `"{$location}: {$message}"`
  - `Donut\Validator\Result` — `add(Problem $p): void`, `getProblems(): array<int, Problem>`, `getErrors(): array<int, Problem>`, `getWarnings(): array<int, Problem>`, `hasErrors(): bool`
  - `Donut\Validator\Validator::__construct(BlockRepository $blocks)`
  - `Donut\Validator\Validator::validate(Workflow $workflow): Result`

**Poznámka k pořadí:** tenhle task implementuje jen kontroly zapojení. Metoda `validate()` v něm ještě nesleduje tok klíčů; Task 7 ji rozšíří. Testy z Tasku 6 musí po Tasku 7 pořád procházet, proto v nich nesmí být workflow, které by v Tasku 7 začalo hlásit chybu navíc — testovací data proto vždy zapisují klíče dřív, než je čtou.

- [ ] **Step 1: Napsat padající test**

`tests/Donut/Validator.steps.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/greet.json', json_encode([
	'name' => 'greet',
	'command' => 'echo',
	'args' => [['{%TEXT%}'], ['{%SUFFIX%}']],
	'inputs' => [
		'TEXT' => ['required' => true],
		'SUFFIX' => ['required' => false],
	],
]));

file_put_contents($dir . '/withStdin.json', json_encode([
	'name' => 'withStdin',
	'command' => 'cat',
	'args' => [],
	'stdin' => ['required' => true],
]));

file_put_contents($dir . '/withDefault.json', json_encode([
	'name' => 'withDefault',
	'command' => 'echo',
	'args' => [['{%A%}']],
	'inputs' => ['A' => ['required' => true, 'default' => 'x']],
]));

file_put_contents($dir . '/badStdinArg.json', json_encode([
	'name' => 'badStdinArg',
	'command' => 'echo',
	'args' => [['{%STDIN%}']],
	'stdin' => ['required' => true],
]));

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$validator = new Validator($repo);

/** @return list<string> */
$messages = function (array $data) use ($parser, $validator): array {
	$result = $validator->validate($parser->parseArray($data, 'w.json'));
	return array_map(strval(...), $result->getErrors());
};

// všechno v pořádku
Assert::same([], $messages([
	'name' => 'w',
	'inputs' => ['T' => ['required' => true]],
	'steps' => [
		['type' => 'run', 'block' => 'greet', 'in' => ['TEXT' => '{%T%}']],
	],
]));

// kámen neexistuje
Assert::same(
	['w.json:steps[0]: kámen "chybi" neexistuje'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'chybi']],
	])
);

// povinný vstup není naplněn
Assert::same(
	['w.json:steps[0]: povinný vstup "TEXT" kamene "greet" není naplněn'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'greet']],
	])
);

// povinný vstup s defaultem naplněn být nemusí
Assert::same([], $messages([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'withDefault']],
]));

// in obsahuje jméno, které kámen nedeklaruje
Assert::same(
	['w.json:steps[0]: kámen "greet" nedeklaruje vstup "NEZNAMY"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}', 'NEZNAMY' => 'x'],
		]],
	])
);

// STDIN u kamene, který stdin nemá
Assert::same(
	['w.json:steps[0]: kámen "greet" nečte stdin, ale krok ho plní'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}', 'STDIN' => 'x'],
		]],
	])
);

// povinný stdin není naplněn
Assert::same(
	['w.json:steps[0]: kámen "withStdin" vyžaduje stdin, krok ho neplní'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'withStdin']],
	])
);

// {%STDIN%} v args kamene
Assert::same(
	['w.json:steps[0]: {%STDIN%} použito v args kamene "badStdinArg"'],
	$messages([
		'name' => 'w',
		'steps' => [[
			'type' => 'run',
			'block' => 'badStdinArg',
			'in' => ['STDIN' => 'x'],
		]],
	])
);

// neznámý kanál v out
Assert::same(
	['w.json:steps[0]: neznámý kanál "stdout"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'run',
			'block' => 'greet',
			'in' => ['TEXT' => '{%T%}'],
			'out' => ['stdout' => 'X'],
		]],
	])
);

// neznámý operátor
Assert::same(
	['w.json:steps[0]: neznámý operátor "matches"'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'matches', 'right' => 'x'],
			'then' => [],
		]],
	])
);

// klíč, na který by pak nešlo odkázat
Assert::same(
	['w.json:steps[0]: klíč "A-B" není platné jméno'],
	$messages([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'A-B', 'value' => 'x']],
	])
);

Assert::same(
	['w.json:steps[0]: klíč "A B" není platné jméno'],
	$messages([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%T%}',
			'as' => 'A B',
			'steps' => [],
		]],
	])
);

Nette\Utils\FileSystem::delete(TEMP_DIR);
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/Validator.steps.phpt`
Expected: FAIL, `Class 'Donut\Validator\Validator' not found`

- [ ] **Step 3: Napsat Problem a Result**

`src/Validator/Problem.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Validator;


final class Problem implements \Stringable
{
	public const Error = 'error';
	public const Warning = 'warning';


	public function __construct(
		public readonly string $severity,
		public readonly string $location,
		public readonly string $message,
	) {
	}


	public static function error(string $location, string $message): self
	{
		return new self(self::Error, $location, $message);
	}


	public static function warning(string $location, string $message): self
	{
		return new self(self::Warning, $location, $message);
	}


	public function __toString(): string
	{
		return "{$this->location}: {$this->message}";
	}
}
```

`src/Validator/Result.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Validator;


final class Result
{
	/** @var array<int, Problem> */
	private array $problems = [];


	public function add(Problem $problem): void
	{
		$this->problems[] = $problem;
	}


	/** @return array<int, Problem> */
	public function getProblems(): array
	{
		return $this->problems;
	}


	/** @return array<int, Problem> */
	public function getErrors(): array
	{
		return \array_values(\array_filter(
			$this->problems,
			fn(Problem $p) => $p->severity === Problem::Error
		));
	}


	/** @return array<int, Problem> */
	public function getWarnings(): array
	{
		return \array_values(\array_filter(
			$this->problems,
			fn(Problem $p) => $p->severity === Problem::Warning
		));
	}


	public function hasErrors(): bool
	{
		return $this->getErrors() !== [];
	}
}
```

- [ ] **Step 4: Napsat Validator s kontrolami zapojení**

`src/Validator/Validator.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Validator;

use Donut\BlockRepository;
use Donut\Format\Block;
use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * Statická validace workflow podle sekce 5 specifikace.
 *
 * Běží před spuštěním prvního kroku. Kontroluje zapojení kroků na kameny
 * a v Tasku 7 i tok klíčů mapou.
 */
final class Validator
{
	public function __construct(
		private readonly BlockRepository $blocks,
	) {
	}


	public function validate(Workflow $workflow): Result
	{
		$result = new Result;
		$this->checkSteps($workflow->steps, $workflow->name . '.json:steps', $result);

		return $result;
	}


	/**
	 * @param array<int, Step> $steps
	 */
	private function checkSteps(array $steps, string $path, Result $result): void
	{
		foreach ($steps as $i => $step) {
			$at = "{$path}[{$i}]";

			if ($step instanceof RunStep) {
				$this->checkRun($step, $at, $result);

			} elseif ($step instanceof IfStep) {
				$this->checkCondition($step->condition, $at, $result);
				$this->checkSteps($step->then, "{$at}.then", $result);
				$this->checkSteps($step->else, "{$at}.else", $result);

			} elseif ($step instanceof SetStep) {
				$this->checkKeyName($step->key, $at, $result);

			} elseif ($step instanceof ForeachStep) {
				$this->checkKeyName($step->as, $at, $result);
				$this->checkSteps($step->steps, "{$at}.steps", $result);
			}
		}
	}


	private function checkRun(RunStep $step, string $at, Result $result): void
	{
		if (!$this->blocks->has($step->block)) {
			$result->add(Problem::error($at, "kámen \"{$step->block}\" neexistuje"));
			return;
		}

		$block = $this->blocks->get($step->block);

		foreach ($step->in as $name => $template) {
			if ($name === 'STDIN') {
				if ($block->stdin === null) {
					$result->add(Problem::error(
						$at,
						"kámen \"{$block->name}\" nečte stdin, ale krok ho plní"
					));
				}

			} elseif (!isset($block->inputs[$name])) {
				$result->add(Problem::error(
					$at,
					"kámen \"{$block->name}\" nedeklaruje vstup \"{$name}\""
				));
			}
		}

		foreach ($block->inputs as $name => $input) {
			if ($input->required && !isset($step->in[$name]) && $input->default === null) {
				$result->add(Problem::error(
					$at,
					"povinný vstup \"{$name}\" kamene \"{$block->name}\" není naplněn"
				));
			}
		}

		if ($block->stdin !== null && $block->stdin->required && !isset($step->in['STDIN'])) {
			$result->add(Problem::error(
				$at,
				"kámen \"{$block->name}\" vyžaduje stdin, krok ho neplní"
			));
		}

		$this->checkStdinNotInArgs($block, $at, $result);

		foreach ($step->out as $channel => $key) {
			if (!\in_array($channel, RunStep::Channels, true)) {
				$result->add(Problem::error($at, "neznámý kanál \"{$channel}\""));
			}

			$this->checkKeyName($key, $at, $result);
		}
	}


	private function checkStdinNotInArgs(Block $block, string $at, Result $result): void
	{
		foreach ($block->args as $group) {
			foreach ($group as $template) {
				if (\in_array('STDIN', $template->getKeys(), true)) {
					$result->add(Problem::error(
						$at,
						"{%STDIN%} použito v args kamene \"{$block->name}\""
					));

					return;
				}
			}
		}
	}


	private function checkCondition(Condition $condition, string $at, Result $result): void
	{
		if (!\in_array($condition->op, Condition::Operators, true)) {
			$result->add(Problem::error($at, "neznámý operátor \"{$condition->op}\""));
		}
	}


	private function checkKeyName(string $key, string $at, Result $result): void
	{
		if (!Template::isKeyName($key)) {
			$result->add(Problem::error(
				$at,
				"klíč \"{$key}\" není platné jméno"
			));
		}
	}
}
```

- [ ] **Step 5: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/Validator.steps.phpt`
Expected: PASS

- [ ] **Step 6: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add src/Validator tests/Donut/Validator.steps.phpt
git commit -m "Validator: kontroly zapojení kroků na kameny"
```

---

### Task 7: Validátor — tok klíčů mapou

Druhá polovina sekce 5. Sleduje, které klíče v daném bodě **jistě** existují a které **možná**. Rozdíl je podstatný: klíč, který nikdo nikdy nezapisuje, je překlep a je to chyba; klíč zapsaný jen ve větvi `if` nebo uvnitř `foreach` je legitimní a jen varuje.

Pravidla:
- Na začátku jsou jistě známé: vstupy workflow, `STDIN`, `CWD`.
- `run` zapisuje klíče z `out`, `set` zapisuje `key`, `foreach` zapisuje `as` a všechno, co zapíší kroky v těle.
- Větev `if` ani tělo `foreach` nemají vlastní scope, ale nemusí proběhnout — proto jejich zápisy jdou do „možná", ne do „jistě".
- Šablona v **podmínce** a ve **`foreach.over`** čte přísně: klíč musí jistě existovat, jinak chyba (i pro „možná"), protože z podmínky se nedá vycouvat.

**Files:**
- Modify: `src/Validator/Validator.php`
- Create: `src/Validator/KeyFlow.php`
- Test: `tests/Donut/Validator.keyFlow.phpt`

**Interfaces:**
- Consumes: vše z Tasku 6.
- Produces:
  - `Donut\Validator\KeyFlow::__construct(array<int, string> $known)`
  - `KeyFlow::isKnown(string $key): bool` / `isMaybe(string $key): bool`
  - `KeyFlow::write(string $key): void` / `writeMaybe(string $key): void`
  - `KeyFlow::markRead(string $key): void`
  - `KeyFlow::branch(): self` — kopie pro větev
  - `KeyFlow::mergeAsMaybe(self $branch): void` — zápisy z větve převezme jako „možná"
  - `KeyFlow::getWritten(): array<int, string>` / `getRead(): array<int, string>`

- [ ] **Step 1: Napsat padající test**

`tests/Donut/Validator.keyFlow.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$dir = TEMP_DIR . '/blocks';
Nette\Utils\FileSystem::createDir($dir);

file_put_contents($dir . '/echo.json', json_encode([
	'name' => 'echo',
	'command' => 'echo',
	'args' => [['{%TEXT%}']],
	'inputs' => ['TEXT' => ['required' => true]],
]));

$repo = new BlockRepository($dir);
$parser = new WorkflowParser;
$validator = new Validator($repo);

$errors = function (array $data) use ($parser, $validator): array {
	return array_map(strval(...), $validator->validate($parser->parseArray($data, 'w.json'))->getErrors());
};

$warnings = function (array $data) use ($parser, $validator): array {
	return array_map(strval(...), $validator->validate($parser->parseArray($data, 'w.json'))->getWarnings());
};

// klíč zapsaný dřív, čtený později
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'A', 'value' => 'x'],
		['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
	],
]));

// STDIN a CWD jsou známé od začátku
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%CWD%}/{%STDIN%}']]],
]));

// klíč, který nikdo nikdy nezapisuje = překlep
Assert::same(
	['w.json:steps[0]: šablona čte klíč "NENI", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%NENI%}']]],
	])
);

// klíč zapsaný až později
Assert::same(
	['w.json:steps[0]: šablona čte klíč "A", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'steps' => [
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
			['type' => 'set', 'key' => 'A', 'value' => 'x'],
		],
	])
);

// zápis ve větvi if je za ifem jen "možná" -> varování
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [
		[
			'type' => 'if',
			'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
			'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
		],
		['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
	],
]));

Assert::contains(
	'w.json:steps[1]: šablona čte klíč "A", který nemusí existovat',
	$warnings([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			],
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
		],
	])
);

// uvnitř větve je zápis z téže větve jistý
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [[
		'type' => 'if',
		'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
		'then' => [
			['type' => 'set', 'key' => 'A', 'value' => 'x'],
			['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%A%}']],
		],
	]],
]));

// foreach: as je uvnitř těla jistý, za cyklem jen možný
Assert::same([], $errors([
	'name' => 'w',
	'inputs' => ['T' => []],
	'steps' => [[
		'type' => 'foreach',
		'over' => '{%T%}',
		'as' => 'LINE',
		'steps' => [['type' => 'run', 'block' => 'echo', 'in' => ['TEXT' => '{%LINE%}']]],
	]],
]));

// podmínka čte přísně: "možná" nestačí
Assert::same(
	['w.json:steps[1]: podmínka čte klíč "A", který v tomto místě nemohl vzniknout'],
	$errors([
		'name' => 'w',
		'inputs' => ['T' => []],
		'steps' => [
			[
				'type' => 'if',
				'condition' => ['left' => '{%T%}', 'op' => 'not_empty'],
				'then' => [['type' => 'set', 'key' => 'A', 'value' => 'x']],
			],
			[
				'type' => 'if',
				'condition' => ['left' => '{%A%}', 'op' => 'eq', 'right' => 'x'],
				'then' => [],
			],
		],
	])
);

// foreach.over čte přísně
Assert::same(
	['w.json:steps[0]: foreach čte klíč "NENI", který žádný krok nezapisuje'],
	$errors([
		'name' => 'w',
		'steps' => [[
			'type' => 'foreach',
			'over' => '{%NENI%}',
			'as' => 'L',
			'steps' => [],
		]],
	])
);

// set smí číst vlastní klíč, když už existuje
Assert::same([], $errors([
	'name' => 'w',
	'steps' => [
		['type' => 'set', 'key' => 'A', 'value' => 'x'],
		['type' => 'set', 'key' => 'A', 'value' => '{%A%} y'],
	],
]));

// varování: klíč se zapisuje a nikdy nečte
Assert::contains(
	'w.json: klíč "NEPOUZITY" se zapisuje a nikdy nečte',
	$warnings([
		'name' => 'w',
		'steps' => [['type' => 'set', 'key' => 'NEPOUZITY', 'value' => 'x']],
	])
);

// varování: vstup workflow se nikde nepoužívá
Assert::contains(
	'w.json: vstup "NEPOUZITY" se nikde nepoužívá',
	$warnings([
		'name' => 'w',
		'inputs' => ['NEPOUZITY' => []],
		'steps' => [],
	])
);

// STDIN a CWD nepoužité nevarují
Assert::same([], $warnings([
	'name' => 'w',
	'steps' => [],
]));

Nette\Utils\FileSystem::delete(TEMP_DIR);
```

- [ ] **Step 2: Spustit test, ověřit že padá**

Run: `vendor/bin/tester -C tests/Donut/Validator.keyFlow.phpt`
Expected: FAIL — hlásí prázdné pole tam, kde se čekají chyby o klíčích.

- [ ] **Step 3: Napsat KeyFlow**

`src/Validator/KeyFlow.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Validator;


/**
 * Které klíče v daném místě workflow existují.
 *
 * „Jistě" = zapsal je krok, který se určitě provedl. „Možná" = zapsal je krok
 * uvnitř větve if nebo těla foreach, které nemusí proběhnout.
 */
final class KeyFlow
{
	/** @var array<string, true> */
	private array $known = [];

	/** @var array<string, true> */
	private array $maybe = [];

	/** @var array<string, true> */
	private array $written = [];

	/** @var array<string, true> */
	private array $read = [];


	/** @param array<int, string> $known */
	public function __construct(array $known = [])
	{
		foreach ($known as $key) {
			$this->known[$key] = true;
		}
	}


	public function isKnown(string $key): bool
	{
		return isset($this->known[$key]);
	}


	public function isMaybe(string $key): bool
	{
		return isset($this->maybe[$key]);
	}


	public function write(string $key): void
	{
		$this->known[$key] = true;
		$this->written[$key] = true;
	}


	public function writeMaybe(string $key): void
	{
		$this->maybe[$key] = true;
		$this->written[$key] = true;
	}


	public function markRead(string $key): void
	{
		$this->read[$key] = true;
	}


	/**
	 * Kopie pro větev if nebo tělo foreach. Sdílí evidenci zapsaných
	 * a přečtených klíčů skrz merge, ale zápisy uvnitř neovlivní volajícího
	 * dřív, než se větev vyhodnotí.
	 */
	public function branch(): self
	{
		$branch = new self;
		$branch->known = $this->known;
		$branch->maybe = $this->maybe;
		$branch->written = $this->written;
		$branch->read = $this->read;

		return $branch;
	}


	/**
	 * Převezme z větve zápisy jako „možná" a evidenci čtení a zápisů.
	 */
	public function mergeAsMaybe(self $branch): void
	{
		foreach (\array_keys($branch->known) as $key) {
			if (!isset($this->known[$key])) {
				$this->maybe[$key] = true;
			}
		}

		foreach (\array_keys($branch->maybe) as $key) {
			$this->maybe[$key] = true;
		}

		$this->written += $branch->written;
		$this->read += $branch->read;
	}


	/** @return array<int, string> */
	public function getWritten(): array
	{
		return \array_keys($this->written);
	}


	/** @return array<int, string> */
	public function getRead(): array
	{
		return \array_keys($this->read);
	}
}
```

- [ ] **Step 4: Rozšířit Validator o tok klíčů**

V `src/Validator/Validator.php` nahraď metodu `validate()` a `checkSteps()` a přidej nové privátní metody. Zbytek souboru (`checkRun`, `checkStdinNotInArgs`, `checkCondition`, `checkKeyName`) zůstává beze změny, jen `checkRun` a `checkSteps` dostanou parametr `KeyFlow`.

Celé `validate()`:

```php
	public function validate(Workflow $workflow): Result
	{
		$result = new Result;
		$location = $workflow->name . '.json';

		$flow = new KeyFlow([...\array_keys($workflow->inputs), 'STDIN', 'CWD']);

		$this->checkSteps($workflow->steps, "{$location}:steps", $result, $flow);

		$read = $flow->getRead();

		foreach ($flow->getWritten() as $key) {
			if (!\in_array($key, $read, true)) {
				$result->add(Problem::warning(
					$location,
					"klíč \"{$key}\" se zapisuje a nikdy nečte"
				));
			}
		}

		foreach (\array_keys($workflow->inputs) as $key) {
			if (!\in_array($key, $read, true)) {
				$result->add(Problem::warning(
					$location,
					"vstup \"{$key}\" se nikde nepoužívá"
				));
			}
		}

		return $result;
	}
```

Celé `checkSteps()`:

```php
	/**
	 * @param array<int, Step> $steps
	 */
	private function checkSteps(array $steps, string $path, Result $result, KeyFlow $flow): void
	{
		foreach ($steps as $i => $step) {
			$at = "{$path}[{$i}]";

			if ($step instanceof RunStep) {
				$this->checkRun($step, $at, $result, $flow);

			} elseif ($step instanceof IfStep) {
				$this->checkCondition($step->condition, $at, $result);
				$this->readStrict($step->condition->left, $at, 'podmínka', $result, $flow);

				if ($step->condition->right !== null) {
					$this->readStrict($step->condition->right, $at, 'podmínka', $result, $flow);
				}

				$then = $flow->branch();
				$this->checkSteps($step->then, "{$at}.then", $result, $then);
				$flow->mergeAsMaybe($then);

				$else = $flow->branch();
				$this->checkSteps($step->else, "{$at}.else", $result, $else);
				$flow->mergeAsMaybe($else);

			} elseif ($step instanceof SetStep) {
				$this->checkKeyName($step->key, $at, $result);
				$this->readTolerant($step->value, $at, 'set', $result, $flow);
				$flow->write($step->key);

			} elseif ($step instanceof ForeachStep) {
				$this->checkKeyName($step->as, $at, $result);
				$this->readStrict($step->over, $at, 'foreach', $result, $flow);

				$body = $flow->branch();
				$body->write($step->as);
				$this->checkSteps($step->steps, "{$at}.steps", $result, $body);
				$flow->mergeAsMaybe($body);
				$flow->writeMaybe($step->as);
			}
		}
	}
```

Uprav hlavičku `checkRun` na `private function checkRun(RunStep $step, string $at, Result $result, KeyFlow $flow): void` a **na konec jejího těla** (za smyčku přes `$step->out`) doplň zápis do mapy. Zároveň hned na začátku metody, ještě před `if (!$this->blocks->has(...))`, přidej čtení šablon z `in`, aby se překlepy hlásily i u kroku s neexistujícím kamenem:

```php
		foreach ($step->in as $template) {
			$this->readTolerant($template, $at, 'šablona', $result, $flow);
		}
```

a do smyčky přes `$step->out` za `checkKeyName` přidej:

```php
			$flow->write($key);
```

Nové privátní metody:

```php
	/**
	 * Čtení, které snese klíč zapsaný jen v jedné větvi — jen varuje.
	 */
	private function readTolerant(
		Template $template,
		string $at,
		string $what,
		Result $result,
		KeyFlow $flow,
	): void
	{
		foreach ($template->getKeys() as $key) {
			$flow->markRead($key);

			if ($flow->isKnown($key)) {
				continue;
			}

			if ($flow->isMaybe($key)) {
				$result->add(Problem::warning(
					$at,
					"{$what} čte klíč \"{$key}\", který nemusí existovat"
				));

			} else {
				$result->add(Problem::error($at, $this->missingKeyMessage($what, $key)));
			}
		}
	}


	/**
	 * Čtení v podmínce a ve foreach.over. Z těch se nedá vycouvat, takže
	 * „možná" nestačí.
	 */
	private function readStrict(
		Template $template,
		string $at,
		string $what,
		Result $result,
		KeyFlow $flow,
	): void
	{
		foreach ($template->getKeys() as $key) {
			$flow->markRead($key);

			if (!$flow->isKnown($key)) {
				$result->add(Problem::error($at, $this->missingKeyMessage($what, $key)));
			}
		}
	}


	/**
	 * Klíč, který nikdo nikdy nezapisuje, je překlep; klíč zapsaný později
	 * je chyba pořadí. Hlášky se liší, aby se to dalo rozlišit.
	 */
	private function missingKeyMessage(string $what, string $key): string
	{
		return \in_array($key, $this->writtenAnywhere, true)
			? "{$what} čte klíč \"{$key}\", který v tomto místě nemohl vzniknout"
			: "{$what} čte klíč \"{$key}\", který žádný krok nezapisuje";
	}
```

Aby `missingKeyMessage()` věděla, co se kde zapisuje, potřebuje validátor projít workflow předem. Přidej vlastnost a její naplnění:

```php
	/** @var array<int, string> */
	private array $writtenAnywhere = [];
```

a na začátek `validate()`, hned za `$location`, vlož:

```php
		$this->writtenAnywhere = $this->collectWrittenKeys($workflow->steps);
```

a přidej metodu:

```php
	/**
	 * Všechny klíče, které kterýkoliv krok kdekoliv zapisuje — bez ohledu
	 * na pořadí a větvení. Slouží k rozlišení překlepu od špatného pořadí.
	 *
	 * @param  array<int, Step> $steps
	 * @return array<int, string>
	 */
	private function collectWrittenKeys(array $steps): array
	{
		$keys = [];

		foreach ($steps as $step) {
			if ($step instanceof RunStep) {
				foreach ($step->out as $key) {
					$keys[] = $key;
				}

			} elseif ($step instanceof SetStep) {
				$keys[] = $step->key;

			} elseif ($step instanceof IfStep) {
				$keys = [
					...$keys,
					...$this->collectWrittenKeys($step->then),
					...$this->collectWrittenKeys($step->else),
				];

			} elseif ($step instanceof ForeachStep) {
				$keys[] = $step->as;
				$keys = [...$keys, ...$this->collectWrittenKeys($step->steps)];
			}
		}

		return \array_values(\array_unique($keys));
	}
```

`KeyFlow` je ve stejném namespace jako `Validator`, takže se neimportuje. `Template` už mezi `use` je z Tasku 6.

**Vědomé zjednodušení:** klíč zapsaný v obou větvích `if` by mohl být za `if` „jistý", ale tahle implementace ho vede jako „možná". Je to konzervativní — vyrobí varování tam, kde by nemuselo být, ale nikdy neprohlásí za jistý klíč, který nemusí vzniknout. Neopravuj to; kdyby to začalo vadit, je to samostatná změna s vlastními testy.

- [ ] **Step 5: Spustit test, ověřit že prochází**

Run: `vendor/bin/tester -C tests/Donut/Validator.keyFlow.phpt`
Expected: PASS

- [ ] **Step 6: Ověřit, že testy z Tasku 6 pořád procházejí**

Run: `make test`
Expected: PASS, všechny testy.

Kdyby `Validator.steps.phpt` začal hlásit chyby navíc o klíčích, znamená to, že jeho testovací data čtou klíč dřív, než ho někdo zapíše. Oprav testovací data, ne validátor.

- [ ] **Step 7: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 8: Commit**

```bash
git add src/Validator tests/Donut/Validator.keyFlow.phpt
git commit -m "Validator: tok klíčů mapou"
```

---

### Task 8: Přijímací test na skutečném přepisu

Přepis v `docs/workflows/donut/` je hotový a prototypem validátoru prošel s nulou chyb a nulou varování. Tenhle test říká, že totéž musí platit i pro produkční implementaci — je to jediná kontrola, která ověřuje parser a validátor proti reálnému formátu, ne proti vymyšleným datům.

**Files:**
- Create: `tests/Donut/acceptance.rewrite.phpt`
- Create: `tests/Donut/acceptance.negative.phpt`

**Interfaces:**
- Consumes: `Donut\BlockRepository`, `Donut\Parser\WorkflowParser`, `Donut\Validator\Validator`.
- Produces: nic; je to koncový test.

- [ ] **Step 1: Napsat přijímací test**

`tests/Donut/acceptance.rewrite.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\BlockRepository;
use Donut\Parser\WorkflowParser;
use Donut\Validator\Validator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$root = __DIR__ . '/../../docs/workflows/donut';

$repo = new BlockRepository($root . '/blocks');
$validator = new Validator($repo);
$parser = new WorkflowParser;

// všechny kameny se načtou
Assert::count(14, $repo->getNames());

foreach ($repo->getNames() as $name) {
	Assert::same($name, $repo->get($name)->name);
}

// každé workflow projde bez chyb a bez varování
$files = glob($root . '/workflows/*.json');
Assert::count(3, $files);

foreach ($files as $file) {
	$workflow = $parser->parseFile($file);
	$result = $validator->validate($workflow);

	$report = implode("\n", array_map(strval(...), $result->getProblems()));

	Assert::same([], $result->getErrors(), "chyby v {$workflow->name}:\n{$report}");
	Assert::same([], $result->getWarnings(), "varování v {$workflow->name}:\n{$report}");
}

// konkrétní očekávání, ať test nezhasne, kdyby se soubory vyprázdnily
$cardDev = $parser->parseFile($root . '/workflows/card-dev.json');
Assert::same('card-dev', $cardDev->name);
Assert::count(27, $cardDev->steps);
Assert::true(isset($cardDev->inputs['SHORT_ID']));
Assert::false($cardDev->inputs['CURLRC']->required);

$sync = $parser->parseFile($root . '/workflows/sync.json');
Assert::same('sync', $sync->name);
Assert::count(7, $sync->steps);
```

Pozn.: `card-dev` má 27 kroků na nejvyšší úrovni a 29 včetně vnořených;
`sync` má 7 na nejvyšší úrovni a 37 včetně vnořených. Test počítá jen
nejvyšší úroveň, protože `$workflow->steps` je jen ta.

- [ ] **Step 2: Spustit test**

Run: `vendor/bin/tester -C tests/Donut/acceptance.rewrite.phpt`
Expected: PASS

Kdyby padl, **neupravuj přepis v `docs/workflows/donut/`, dokud nevyloučíš chybu v parseru nebo validátoru.** Přepis prošel prototypem validátoru; rozdíl znamená, že se produkční implementace chová jinak než návrh, a to je informace, kterou je potřeba nahlásit, ne přebít úpravou dat.

- [ ] **Step 3: Napsat negativní test**

`tests/Donut/acceptance.negative.phpt`:

```php
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
	'card-dev.json:steps[1]: šablona čte klíč "ME_JSN", který žádný krok nezapisuje',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['STDIN'] = '{%ME_JSN%}';
	})
);

// nedeklarovaný vstup
Assert::contains(
	'card-dev.json:steps[1]: kámen "jq" nedeklaruje vstup "NEZNAMY"',
	$errorsAfter(function (array &$data): void {
		$data['steps'][1]['in']['NEZNAMY'] = 'x';
	})
);

// chybějící povinný stdin
Assert::contains(
	'card-dev.json:steps[1]: kámen "jq" vyžaduje stdin, krok ho neplní',
	$errorsAfter(function (array &$data): void {
		unset($data['steps'][1]['in']['STDIN']);
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
```

- [ ] **Step 4: Spustit testy**

Run: `make test`
Expected: PASS, všechny testy.

- [ ] **Step 5: PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

- [ ] **Step 6: Commit**

```bash
git add tests/Donut/acceptance.rewrite.phpt tests/Donut/acceptance.negative.phpt
git commit -m "Přijímací testy parseru a validátoru na skutečném přepisu"
```

---

## Co tenhle plán vědomě nedělá

- **Runner.** Žádné spouštění procesů, žádný `Nette\Utils\Process`. Ověřeno, že `Process::runExecutable()` v nette/utils 4.1.5 existuje a má parametry, které formát potřebuje (`list<string> $arguments`, `mixed $stdin`, `?string $directory`, `?float $timeout` s null pro vypnutí).
- **CLI wrapper.** `--list`, `--help`, parsování `--KLIC=hodnota`.
- **Skupiny argumentů.** Vypadávání skupiny s prázdnou hodnotou je věc runneru, ne validátoru. `Block::$args` je jen načtené.
- **Souborové výstupy.** Formát je nezná, viz sekce 6 specifikace.
