# Serializér — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Složit `Workflow` a `Block` objekty zpátky do souboru, aby nad tím mohl stavět builder v GUI.

**Architecture:** `Donut\Writer\BlockWriter` a `WorkflowWriter`, symetricky k parserům. Každý umí složit objekt do pole a zapsat ho do souboru přes `Nette\Utils\Json::encode($data, Json::PRETTY)`. Cestu dostane zvenčí a jen ověří, že jméno souboru odpovídá `name`. Sdílené `InputWriter` pro `inputs`, protože je používají oba.

**Tech Stack:** PHP 8.1+, `nette/utils`, `nette/tester`, PHPStan level max.

**Spec:** `docs/superpowers/specs/2026-08-06-serializer-design.md`

## Global Constraints

- PHP 8.1+, **tabulátory** jako odsazení, `declare(strict_types=1);` v každém souboru, dvě prázdné řádky mezi metodami — přesně jako okolní kód.
- **Uživatelské texty a komentáře v donutu jsou česky**, kód a identifikátory anglicky.
- **Formát výstupu je `Nette\Utils\Json::encode($data, Json::PRETTY)`** — žádný vlastní formátovač. To, že `card-dev.json` naroste ze 170 na 353 řádků, je vědomě přijatá cena.
- **`required` se vypisuje vždycky** (u vstupů i u `stdin`). Ostatní volitelná pole — `description`, `default`, `timeout`, `allow_failure`, `stdin`, `name` — jen když jsou vyplněná.
- **Zapisovač cestu dostane, neodvozuje ji.** Ověří, že `basename($path, '.json')` odpovídá `name`, a jinak vyhodí výjimku.
- **Soubor končí novým řádkem** — všech 19 souborů referenční zátěže tak končí a git to má rád.
- **Vrstva nic nespouští a nikam nesahá mimo zadanou cestu.** Zapisovač je pro autorské nástroje; `Runner` ani CLI ho nedostanou.
- Po každém tasku musí projít `vendor/bin/tester tests -C` i `vendor/bin/phpstan analyse` (level max, `src` i `tests`) z kořene repozitáře. Sada má dnes 28 testů.
- `git add` s konkrétními cestami, **nikdy** `git add -A` ani `git add .` — v pracovním stromu jsou čtyři nesledované položky (`.github/workflows/frontbot.yml`, `docs/logo.png`, `donut-org_donut.sublime-workspace`, `rss`), které do commitu nepatří.
- **Nesahej na `gui/`.** Celý tenhle plán je v donutu.

## Pořadí klíčů

Zapisovač je má produkovat v tomtéž pořadí, v jakém je mají soubory dnes —
ověřeno na referenční zátěži:

| | pořadí |
|---|---|
| kámen | `name`, `description`, `command`, `args`, `inputs`, `stdin`, `timeout`, `allow_failure` |
| workflow | `name`, `description`, `inputs`, `steps` |
| krok `run` | `type`, `name`, `block`, `in`, `out`, `timeout`, `allow_failure` |
| krok `if` | `type`, `name`, `condition` (`left`, `op`, `right`), `then`, `else` |
| krok `set` | `type`, `name`, `key`, `value` |
| krok `foreach` | `type`, `name`, `over`, `as`, `steps` |

## Struktura souborů

```
src/Writer/WriteException.php     vlastní typ, jako má parser i runner
src/Writer/InputWriter.php        inputs — používají ho oba zapisovače
src/Writer/BlockWriter.php        Block → pole → soubor
src/Writer/WorkflowWriter.php     Workflow → pole → soubor
tests/Donut/BlockWriter.phpt      jednotkové testy, včetně polí, která
                                  referenční zátěž nepoužívá
tests/Donut/WorkflowWriter.phpt   totéž pro workflow a všechny čtyři kroky
tests/Donut/Writer.roundTrip.phpt zápis do souboru a round-trip nad
                                  referenční zátěží
```

---

### Task 1: `BlockWriter` a `InputWriter`

**Files:**
- Create: `src/Writer/InputWriter.php`, `src/Writer/BlockWriter.php`
- Create: `tests/Donut/BlockWriter.phpt`

**Interfaces:**
- Consumes: `Donut\Format\Block` (`$name`, `$command`, `$args` jako `array<int, array<int, Template>>`, `$inputs` jako `array<string, Input>`, `$stdin` jako `?StdinSpec`, `$timeout` jako `?int`, `$allowFailure` jako `bool|array<int,int>`, `$description`), `Donut\Format\Input` (`$name`, `$required`, `$default`, `$description`), `Donut\Format\StdinSpec` (`$required`, `$description`), `Donut\Template::getSource()`.
- Produces:
  - `Donut\Writer\InputWriter::toArray(array $inputs): array` — `array<string, Input>` → `array<string, array<string, mixed>>`
  - `Donut\Writer\BlockWriter::toArray(Block $block): array`

- [ ] **Step 1: Napiš padající test**

Vytvoř `tests/Donut/BlockWriter.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Format\Input;
use Donut\Format\StdinSpec;
use Donut\Template;
use Donut\Writer\BlockWriter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$writer = new BlockWriter;

// Nejmenší platný kámen: jen povinná pole. Nic volitelného se nesmí objevit.
Assert::same(
	[
		'name' => 'holy',
		'command' => 'echo',
		'args' => [],
	],
	$writer->toArray(new Block(name: 'holy', command: 'echo', args: [])),
);

// Kámen se vším. Pořadí klíčů je součástí tvrzení — Assert::same porovnává
// pole včetně pořadí, takže tenhle test hlídá i to.
$plny = new Block(
	name: 'plny',
	command: 'curl',
	args: [
		[Template::parse('-sS'), Template::parse('--fail')],
		[Template::parse('--config'), Template::parse('{%curlrc%}')],
	],
	inputs: [
		'url' => new Input(name: 'url', required: true, description: 'Adresa'),
		// default se v referenční zátěži nevyskytuje ani jednou — kdyby ho
		// zapisovač zahodil, round-trip nad ní by to nepoznal.
		'curlrc' => new Input(name: 'curlrc', required: false, default: '/tmp/x', description: 'Soubor'),
		'holy' => new Input(name: 'holy'),
	],
	stdin: new StdinSpec(required: false, description: 'Tělo'),
	timeout: 30,
	allowFailure: [0, 1],
	description: 'Popis kamene',
);

Assert::same(
	[
		'name' => 'plny',
		'description' => 'Popis kamene',
		'command' => 'curl',
		'args' => [
			['-sS', '--fail'],
			['--config', '{%curlrc%}'],
		],
		'inputs' => [
			'url' => ['required' => true, 'description' => 'Adresa'],
			'curlrc' => ['required' => false, 'default' => '/tmp/x', 'description' => 'Soubor'],
			// required se vypisuje vždycky, i když je výchozí
			'holy' => ['required' => true],
		],
		'stdin' => ['required' => false, 'description' => 'Tělo'],
		'timeout' => 30,
		'allow_failure' => [0, 1],
	],
	$writer->toArray($plny),
);

// stdin bez popisu má jen required
Assert::same(
	['required' => true],
	$writer->toArray(new Block(
		name: 'x', command: 'cat', args: [],
		stdin: new StdinSpec,
	))['stdin'],
);

// allow_failure: true se vypíše, false se vynechá
Assert::same(
	true,
	$writer->toArray(new Block(name: 'x', command: 'c', args: [], allowFailure: true))['allow_failure'],
);

Assert::false(
	\array_key_exists('allow_failure', $writer->toArray(
		new Block(name: 'x', command: 'c', args: [], allowFailure: false)
	)),
);

// prázdné inputs se vynechají, prázdné args ne — args jsou povinné
$holy = $writer->toArray(new Block(name: 'x', command: 'c', args: []));
Assert::false(\array_key_exists('inputs', $holy));
Assert::true(\array_key_exists('args', $holy));
```

- [ ] **Step 2: Spusť test a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut && vendor/bin/tester tests/Donut/BlockWriter.phpt -C`
Expected: FAIL — `Donut\Writer\BlockWriter` neexistuje.

- [ ] **Step 3: Napiš `InputWriter`**

Vytvoř `src/Writer/InputWriter.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Input;


/**
 * Vstupy do pole. Používají ho oba zapisovače — kámen i workflow mají
 * `inputs` ve stejném tvaru, takže to pravidlo má jedno místo.
 */
final class InputWriter
{
	/**
	 * @param  array<string, Input> $inputs
	 * @return array<string, array<string, mixed>>
	 */
	public static function toArray(array $inputs): array
	{
		$data = [];

		foreach ($inputs as $name => $input) {
			// required se vypisuje vždycky, i když je výchozí — je tak
			// u všech vstupů referenční zátěže a uložením se soubor
			// nemá měnit víc, než je nutné.
			$spec = ['required' => $input->required];

			if ($input->default !== null) {
				$spec['default'] = $input->default;
			}

			if ($input->description !== null) {
				$spec['description'] = $input->description;
			}

			$data[$name] = $spec;
		}

		return $data;
	}
}
```

- [ ] **Step 4: Napiš `BlockWriter`**

Vytvoř `src/Writer/BlockWriter.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\Block;
use Donut\Template;


/**
 * Block na pole. Inverze BlockParseru.
 *
 * Pořadí klíčů odpovídá tomu, jak jsou soubory psané dnes, aby se uložením
 * změnily co nejmíň. Volitelná pole se vynechávají, když nejsou vyplněná —
 * s jedinou výjimkou `required`, které se vypisuje vždycky.
 */
final class BlockWriter
{
	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Block $block): array
	{
		$data = ['name' => $block->name];

		if ($block->description !== null) {
			$data['description'] = $block->description;
		}

		$data['command'] = $block->command;

		$data['args'] = \array_map(
			fn(array $group): array => \array_map(
				fn(Template $template): string => $template->getSource(),
				$group,
			),
			$block->args,
		);

		if ($block->inputs !== []) {
			$data['inputs'] = InputWriter::toArray($block->inputs);
		}

		if ($block->stdin !== null) {
			$stdin = ['required' => $block->stdin->required];

			if ($block->stdin->description !== null) {
				$stdin['description'] = $block->stdin->description;
			}

			$data['stdin'] = $stdin;
		}

		if ($block->timeout !== null) {
			$data['timeout'] = $block->timeout;
		}

		// U kamene je false výchozí hodnota, ne „nenastaveno" — na rozdíl
		// od kroku, kde je výchozí null a false znamená vědomé vypnutí.
		if ($block->allowFailure !== false) {
			$data['allow_failure'] = $block->allowFailure;
		}

		return $data;
	}
}
```

- [ ] **Step 5: Spusť test a ověř, že prochází**

Run: `vendor/bin/tester tests/Donut/BlockWriter.phpt -C`
Expected: PASS.

- [ ] **Step 6: Ověř mutací, že test něco drží**

Zaveď do `src/Writer/BlockWriter.php` postupně tyhle tři chyby a po každé
spusť test:

1. v `InputWriter` vynech `default`
2. u `stdin` vypisuj `required` jen když je `false`
3. změň podmínku u `allow_failure` na `!== null`

Expected: každá shodí `tests/Donut/BlockWriter.phpt`.

Po každé mutaci ji vrať. Nakonec ověř `git status --porcelain src/`, že je
prázdný. **Co některá mutace projde, napiš do reportu** — v tomhle projektu
už dvakrát prošel test proti rozbité implementaci.

- [ ] **Step 7: Spusť celou sadu a PHPStan**

Run: `vendor/bin/tester tests -C && vendor/bin/phpstan analyse`
Expected: 29 testů (bylo 28), PHPStan bez chyb.

- [ ] **Step 8: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut
git add src/Writer/InputWriter.php src/Writer/BlockWriter.php tests/Donut/BlockWriter.phpt
git commit -m "Writer: Block do pole"
```

---

### Task 2: `WorkflowWriter`

**Files:**
- Create: `src/Writer/WorkflowWriter.php`
- Create: `tests/Donut/WorkflowWriter.phpt`

**Interfaces:**
- Consumes: `Donut\Writer\InputWriter::toArray()` z Tasku 1. `Donut\Format\Workflow` (`$name`, `$inputs`, `$steps`, `$description`), `RunStep` (`$block`, `$in` jako `array<string, Template>`, `$out` jako `array<string, string>`, `$timeout`, `$allowFailure` jako `bool|array|null`, `$name`), `IfStep` (`$condition`, `$then`, `$else`, `$name`), `Condition` (`$left`, `$op`, `$right` jako `?Template`), `SetStep` (`$key`, `$value`, `$name`), `ForeachStep` (`$over`, `$as`, `$steps`, `$name`).
- Produces: `Donut\Writer\WorkflowWriter::toArray(Workflow $workflow): array`

- [ ] **Step 1: Napiš padající test**

Vytvoř `tests/Donut/WorkflowWriter.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\Input;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Workflow;
use Donut\Template;
use Donut\Writer\WorkflowWriter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$writer = new WorkflowWriter;

// Nejmenší platné workflow: jen povinná pole. steps se vypisují i prázdné.
Assert::same(
	['name' => 'holy', 'steps' => []],
	$writer->toArray(new Workflow(name: 'holy')),
);

// Krok run se vším. timeout a allow_failure se v referenční zátěži
// u kroku nevyskytují ani jednou — kdyby je zapisovač zahodil,
// round-trip nad ní by to nepoznal.
Assert::same(
	[
		'type' => 'run',
		'name' => 'pojmenovaný',
		'block' => 'jq',
		'in' => ['stdin' => '{%vstup%}', 'filter' => '.id'],
		'out' => ['result' => 'vysledek', 'exit_code' => 'kod'],
		'timeout' => 90,
		'allow_failure' => [0, 1],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(
			block: 'jq',
			in: ['stdin' => Template::parse('{%vstup%}'), 'filter' => Template::parse('.id')],
			out: ['result' => 'vysledek', 'exit_code' => 'kod'],
			timeout: 90,
			allowFailure: [0, 1],
			name: 'pojmenovaný',
		),
	]))['steps'][0],
);

// Krok run bez ničeho volitelného. allow_failure: null znamená
// „nenastaveno" a nesmí se objevit; u kroku je to jiné než u kamene.
Assert::same(
	['type' => 'run', 'block' => 'echo'],
	$writer->toArray(new Workflow(name: 'w', steps: [new RunStep(block: 'echo')]))['steps'][0],
);

// allow_failure: false u kroku je vědomé vypnutí, ne výchozí stav —
// musí se vypsat.
Assert::same(
	['type' => 'run', 'block' => 'echo', 'allow_failure' => false],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new RunStep(block: 'echo', allowFailure: false),
	]))['steps'][0],
);

// Krok set. name u setu se v referenční zátěži nevyskytuje ani jednou.
Assert::same(
	['type' => 'set', 'name' => 'pojmenovaný set', 'key' => 'klic', 'value' => 'a {%b%}'],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new SetStep(key: 'klic', value: Template::parse('a {%b%}'), name: 'pojmenovaný set'),
	]))['steps'][0],
);

// Krok if s oběma větvemi a s right
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'eq', 'right' => '{%b%}'],
		'then' => [['type' => 'set', 'key' => 'x', 'value' => '1']],
		'else' => [['type' => 'set', 'key' => 'x', 'value' => '2']],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(
			condition: new Condition(
				left: Template::parse('{%a%}'),
				op: 'eq',
				right: Template::parse('{%b%}'),
			),
			then: [new SetStep(key: 'x', value: Template::parse('1'))],
			else: [new SetStep(key: 'x', value: Template::parse('2'))],
		),
	]))['steps'][0],
);

// Krok if bez right a s prázdnou větví else. then se vypisuje i prázdné,
// protože ho parser vyžaduje; else se vynechá.
Assert::same(
	[
		'type' => 'if',
		'condition' => ['left' => '{%a%}', 'op' => 'not_empty'],
		'then' => [],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new IfStep(condition: new Condition(left: Template::parse('{%a%}'), op: 'not_empty')),
	]))['steps'][0],
);

// Krok foreach včetně vnořeného kroku
Assert::same(
	[
		'type' => 'foreach',
		'over' => '{%seznam%}',
		'as' => 'radek',
		'steps' => [['type' => 'set', 'key' => 'x', 'value' => '{%radek%}']],
	],
	$writer->toArray(new Workflow(name: 'w', steps: [
		new ForeachStep(
			over: Template::parse('{%seznam%}'),
			as: 'radek',
			steps: [new SetStep(key: 'x', value: Template::parse('{%radek%}'))],
		),
	]))['steps'][0],
);

// Hlavička workflow: pořadí klíčů a vynechání prázdných inputs
Assert::same(
	[
		'name' => 'plne',
		'description' => 'Popis',
		'inputs' => [
			'a' => ['required' => true, 'description' => 'Áčko'],
			'b' => ['required' => false, 'default' => 'x'],
		],
		'steps' => [],
	],
	$writer->toArray(new Workflow(
		name: 'plne',
		inputs: [
			'a' => new Input(name: 'a', description: 'Áčko'),
			'b' => new Input(name: 'b', required: false, default: 'x'),
		],
		description: 'Popis',
	)),
);
```

- [ ] **Step 2: Spusť test a ověř, že padá**

Run: `vendor/bin/tester tests/Donut/WorkflowWriter.phpt -C`
Expected: FAIL — `Donut\Writer\WorkflowWriter` neexistuje.

- [ ] **Step 3: Napiš `WorkflowWriter`**

Vytvoř `src/Writer/WorkflowWriter.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * Workflow na pole. Inverze WorkflowParseru.
 *
 * Pořadí klíčů odpovídá tomu, jak jsou soubory psané dnes. Volitelná pole
 * se vynechávají, když nejsou vyplněná; `required` u vstupů se vypisuje
 * vždycky (viz InputWriter).
 */
final class WorkflowWriter
{
	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Workflow $workflow): array
	{
		$data = ['name' => $workflow->name];

		if ($workflow->description !== null) {
			$data['description'] = $workflow->description;
		}

		if ($workflow->inputs !== []) {
			$data['inputs'] = InputWriter::toArray($workflow->inputs);
		}

		// steps se vypisují i prázdné — parser je vyžaduje.
		$data['steps'] = $this->stepsToArray($workflow->steps);

		return $data;
	}


	/**
	 * @param  array<int, Step> $steps
	 * @return array<int, array<string, mixed>>
	 */
	private function stepsToArray(array $steps): array
	{
		return \array_map(fn(Step $step): array => $this->stepToArray($step), $steps);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function stepToArray(Step $step): array
	{
		if ($step instanceof RunStep) {
			$data = ['type' => 'run'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['block'] = $step->block;

			if ($step->in !== []) {
				$data['in'] = \array_map(
					fn(Template $template): string => $template->getSource(),
					$step->in,
				);
			}

			if ($step->out !== []) {
				$data['out'] = $step->out;
			}

			if ($step->timeout !== null) {
				$data['timeout'] = $step->timeout;
			}

			// U kroku je null „nenastaveno" a false vědomé vypnutí —
			// na rozdíl od kamene, kde je false výchozí hodnota.
			if ($step->allowFailure !== null) {
				$data['allow_failure'] = $step->allowFailure;
			}

			return $data;
		}

		if ($step instanceof SetStep) {
			$data = ['type' => 'set'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['key'] = $step->key;
			$data['value'] = $step->value->getSource();

			return $data;
		}

		if ($step instanceof IfStep) {
			$data = ['type' => 'if'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$condition = [
				'left' => $step->condition->left->getSource(),
				'op' => $step->condition->op,
			];

			if ($step->condition->right !== null) {
				$condition['right'] = $step->condition->right->getSource();
			}

			$data['condition'] = $condition;
			// then se vypisuje i prázdné — parser ho vyžaduje.
			$data['then'] = $this->stepsToArray($step->then);

			if ($step->else !== []) {
				$data['else'] = $this->stepsToArray($step->else);
			}

			return $data;
		}

		if ($step instanceof ForeachStep) {
			$data = ['type' => 'foreach'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['over'] = $step->over->getSource();
			$data['as'] = $step->as;
			$data['steps'] = $this->stepsToArray($step->steps);

			return $data;
		}

		// Nová implementace Step se nesmí tiše přeskočit — spadlo by to až
		// tím, že by z uloženého souboru zmizel celý krok.
		throw new \LogicException('neznámý typ kroku ' . $step::class);
	}
}
```

- [ ] **Step 4: Spusť test a ověř, že prochází**

Run: `vendor/bin/tester tests/Donut/WorkflowWriter.phpt -C`
Expected: PASS.

- [ ] **Step 5: Ověř mutací, že test něco drží**

Zaveď do `src/Writer/WorkflowWriter.php` postupně tyhle čtyři chyby a po každé
spusť test:

1. u `RunStep` změň podmínku u `allow_failure` na `!== false`
2. u `SetStep` vynech `name`
3. u `IfStep` vypisuj `then` jen když není prázdné
4. u `ForeachStep` zaměň `over` a `as`

Expected: každá shodí `tests/Donut/WorkflowWriter.phpt`.

Po každé mutaci ji vrať a nakonec ověř `git status --porcelain src/`.
**Co některá mutace projde, napiš do reportu.**

- [ ] **Step 6: Ověř, že se `LogicException` dá vyvolat**

`Donut\Format\Step` je rozhraní, takže pátý typ jde zkonstruovat. Ověř
jednorázově, že závěrečná větev doopravdy hoří:

Run:
```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut
php -r '
require "vendor/autoload.php";
$krok = new class implements Donut\Format\Step {};
try {
    (new Donut\Writer\WorkflowWriter)->toArray(new Donut\Format\Workflow(name: "w", steps: [$krok]));
    echo "NEHODILO — chyba\n";
} catch (LogicException $e) {
    echo "OK: ", $e->getMessage(), "\n";
}'
```
Expected: `OK: neznámý typ kroku …`

Kdyby `Step` měl povinné metody a anonymní třída nešla vytvořit, zapiš to do
reportu a pokračuj — je to ověření, ne požadavek.

- [ ] **Step 7: Spusť celou sadu a PHPStan**

Run: `vendor/bin/tester tests -C && vendor/bin/phpstan analyse`
Expected: 30 testů (bylo 29), PHPStan bez chyb.

- [ ] **Step 8: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut
git add src/Writer/WorkflowWriter.php tests/Donut/WorkflowWriter.phpt
git commit -m "Writer: Workflow do pole"
```

---

### Task 3: Zápis do souboru a round-trip

Teprve tady vzniká soubor. Round-trip nad referenční zátěží je pojistka proti
tomu, že by zapisovač na něčem skutečném selhal — jednotkové testy z Tasků 1
a 2 pokrývají pole, která zátěž nepoužívá.

**Files:**
- Create: `src/Writer/WriteException.php`
- Modify: `src/Writer/BlockWriter.php` (přibude `writeFile()`)
- Modify: `src/Writer/WorkflowWriter.php` (přibude `writeFile()`)
- Create: `tests/Donut/Writer.roundTrip.phpt`

**Interfaces:**
- Consumes: `BlockWriter::toArray()` a `WorkflowWriter::toArray()` z Tasků 1 a 2; `Donut\Parser\BlockParser::parseFile()`, `WorkflowParser::parseFile()`.
- Produces:
  - `Donut\Writer\WriteException extends Donut\Exception`
  - `BlockWriter::writeFile(Block $block, string $path): void`
  - `WorkflowWriter::writeFile(Workflow $workflow, string $path): void`
  - Obojí hodí `WriteException`, když `basename($path, '.json')` neodpovídá `name`.

- [ ] **Step 1: Napiš padající test**

Vytvoř `tests/Donut/Writer.roundTrip.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Format\Block;
use Donut\Parser\BlockParser;
use Donut\Parser\WorkflowParser;
use Donut\Writer\BlockWriter;
use Donut\Writer\WorkflowWriter;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$blockParser = new BlockParser;
$workflowParser = new WorkflowParser;
$blockWriter = new BlockWriter;
$workflowWriter = new WorkflowWriter;

$root = __DIR__ . '/../../docs/workflows/donut';
$temp = TEMP_DIR . '/writer';
FileSystem::createDir($temp);

// --- round-trip nad referenční zátěží ---
//
// Objekt → soubor → objekt. Porovnává se přes ==, které na těchhle
// objektech drží strukturálně včetně Template.
//
// Pozor: sama tahle zátěž NESTAČÍ. Pět volitelných polí se v ní
// nevyskytuje ani jednou (default u vstupů, timeout a allow_failure
// u kroku, name u setu) — ta hlídají BlockWriter.phpt a WorkflowWriter.phpt.

$blocks = \glob($root . '/blocks/*.json');
Assert::count(15, $blocks === false ? [] : $blocks);

foreach ($blocks === false ? [] : $blocks as $path) {
	$puvodni = $blockParser->parseFile($path);
	$cil = $temp . '/' . \basename($path);

	$blockWriter->writeFile($puvodni, $cil);
	$znovu = $blockParser->parseFile($cil);

	Assert::equal($puvodni, $znovu, 'round-trip kamene ' . \basename($path));
}

$workflows = \glob($root . '/workflows/*.json');
Assert::count(4, $workflows === false ? [] : $workflows);

foreach ($workflows === false ? [] : $workflows as $path) {
	$puvodni = $workflowParser->parseFile($path);
	$cil = $temp . '/' . \basename($path);

	$workflowWriter->writeFile($puvodni, $cil);
	$znovu = $workflowParser->parseFile($cil);

	Assert::equal($puvodni, $znovu, 'round-trip workflow ' . \basename($path));
}

// --- soubor končí novým řádkem ---
// Všech 19 souborů referenční zátěže tak končí a git to má rád.

$obsah = FileSystem::read($temp . '/card-dev.json');
Assert::same("\n", \substr($obsah, -1));

// --- jméno musí odpovídat souboru ---
// Parser to při čtení vynucuje; zapisovač to hlídá při zápisu, aby ta dvě
// pravidla nemohla přestat platit současně.

Assert::exception(
	fn() => $blockWriter->writeFile(
		new Block(name: 'jedno', command: 'echo', args: []),
		$temp . '/druhe.json',
	),
	Donut\Writer\WriteException::class,
);

Assert::exception(
	fn() => $workflowWriter->writeFile(
		new Donut\Format\Workflow(name: 'jedno'),
		$temp . '/druhe.json',
	),
	Donut\Writer\WriteException::class,
);

// Správné jméno projde
$blockWriter->writeFile(new Block(name: 'spravne', command: 'echo', args: []), $temp . '/spravne.json');
Assert::same('spravne', $blockParser->parseFile($temp . '/spravne.json')->name);

FileSystem::delete(TEMP_DIR);
```

- [ ] **Step 2: Spusť test a ověř, že padá**

Run: `vendor/bin/tester tests/Donut/Writer.roundTrip.phpt -C`
Expected: FAIL — `writeFile()` neexistuje.

- [ ] **Step 3: Napiš `WriteException`**

Každá vrstva donutu má vlastní typ výjimky — `ParseException`,
`RunFailedException`, `CannotStartException`, `UsageException`. Zapisovač
dostane svůj, aby ho volající uměl chytit zvlášť.

Vytvoř `src/Writer/WriteException.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Exception;


/**
 * Objekt nejde zapsat do zadané cesty.
 */
final class WriteException extends Exception
{
}
```

- [ ] **Step 4: Doplň `writeFile()` do `BlockWriter`**

Do `src/Writer/BlockWriter.php` přidej `use` a metodu:

```php
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
```

```php
	/**
	 * Cesta se dostává zvenčí, neodvozuje se ze jména: repository už ji pro
	 * každé známé jméno drží a druhý výklad téhož pravidla by se s ním mohl
	 * rozejít. Kontroluje se ale, že spolu sedí — parser to při čtení
	 * vynucuje taky.
	 *
	 * @throws WriteException když jméno kamene neodpovídá názvu souboru
	 */
	public function writeFile(Block $block, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($block->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		FileSystem::write($path, Json::encode($this->toArray($block), Json::PRETTY) . "\n");
	}
```

- [ ] **Step 5: Doplň `writeFile()` do `WorkflowWriter`**

Do `src/Writer/WorkflowWriter.php` přidej tytéž `use` a metodu:

```php
	/**
	 * Cesta se dostává zvenčí, neodvozuje se ze jména — viz BlockWriter.
	 *
	 * @throws WriteException když jméno workflow neodpovídá názvu souboru
	 */
	public function writeFile(Workflow $workflow, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($workflow->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$workflow->name}' neodpovídá názvu souboru '{$expected}'."
			);
		}

		FileSystem::write($path, Json::encode($this->toArray($workflow), Json::PRETTY) . "\n");
	}
```

- [ ] **Step 6: Spusť test a ověř, že prochází**

Run: `vendor/bin/tester tests/Donut/Writer.roundTrip.phpt -C`
Expected: PASS.

Kdyby round-trip spadl na některém souboru referenční zátěže,
**neupravuj test, aby prošel** — znamená to, že zapisovač některé pole
nepřenáší. Vypiš si rozdíl mezi původním a znovu naparsovaným objektem,
zjisti, které pole chybí, a oprav zapisovač.

- [ ] **Step 7: Ověř, že round-trip doopravdy něco drží**

Zaveď do `src/Writer/WorkflowWriter.php` chybu — u `RunStep` vynech `out` —
a spusť test.

Expected: FAIL na round-tripu prvního workflow, protože `out` používá
60 kroků referenční zátěže.

Pak mutaci vrať a ověř `git status --porcelain src/`.

- [ ] **Step 8: Podívej se, jak uložený soubor vypadá**

Návrh přijal, že `card-dev.json` naroste ze 170 na 353 řádků. Ověř to
a zapiš skutečná čísla do reportu — je to viditelný důsledek celého projektu.

Run:
```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut
php -r '
require "vendor/autoload.php";
$p = new Donut\Parser\WorkflowParser;
$w = new Donut\Writer\WorkflowWriter;
$tmp = sys_get_temp_dir() . "/card-dev.json";
$w->writeFile($p->parseFile("docs/workflows/donut/workflows/card-dev.json"), $tmp);
printf("dnes: %d řádků, po uložení: %d\n",
    substr_count(file_get_contents("docs/workflows/donut/workflows/card-dev.json"), "\n"),
    substr_count(file_get_contents($tmp), "\n"));
unlink($tmp);'
```

**Do referenční zátěže nic nezapisuj** — `git status --porcelain docs/` musí
zůstat prázdný.

- [ ] **Step 9: Spusť celou sadu a PHPStan**

Run: `vendor/bin/tester tests -C && vendor/bin/phpstan analyse`
Expected: 31 testů (bylo 30), PHPStan bez chyb.

- [ ] **Step 10: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut
git add src/Writer/WriteException.php src/Writer/BlockWriter.php src/Writer/WorkflowWriter.php tests/Donut/Writer.roundTrip.phpt
git commit -m "Writer: zápis do souboru a round-trip nad referenční zátěží"
```
