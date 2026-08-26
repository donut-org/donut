# Anglické texty aplikace — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Všechno, co je součástí kódu — hlášky, výjimky, UI v šablonách, komentáře, identifikátory v testech — mluví anglicky. Česky zůstává jen dokumentace a Honzův vlastní obsah.

**Architecture:** Deset tasků zdola nahoru, vrstva po vrstvě. V každém se překládají řetězce, komentáře **i testy té vrstvy najednou**, takže sada je zelená po každém tasku. Glosář ze specifikace je závazný slovník; bez něj se 113 hlášek rozejde. Šablony se nedělí od testů prezentérů, protože 92 ze 175 českých asercí v GUI tvrdí o vyrenderovaném HTML.

**Tech Stack:** PHP 8.1+ (jádro) / 8.3+ (`gui/`), nette/utils, nette/application 3.2, Latte 3, nette/tester, PHPStan level max.

**Spec:** `docs/superpowers/specs/2026-08-26-anglicke-texty-design.md`

## Global Constraints

- **Komentáře anglicky, commit messages anglicky.** Tohle je obrácení dosavadní konvence projektu: plány do 2026-08-25 psaly „komentáře česky" a commity byly česky. **Bylo to špatně.** Nekopíruj tu větu ze starších plánů. Komentář dál vysvětluje *proč*, ne *co* — mění se jazyk, ne povaha.
- **Odsazení tabulátory.** Beze změny.
- **PHPStan level max** v obou balíčcích. Ověřuj `make phpstan` (kořen) a `cd gui && make phpstan`. Čtyři pasti, na kterých kód z plánů v tomhle projektu opakovaně padá:
  1. `(string) $mixed` je `cast.string` — použij `is_string()` guard nebo pomocnou metodu.
  2. `Donut\Format` typuje kolekce jako `array<int, Step>`, ne `list<Step>` — anotace `list<…>` neprojde.
  3. `$form::Filled` neprojde, `Form::Filled` ano.
  4. `$nullable?->prop ?? $default` je `nullsafe.neverNull` — rozděl do meziproměnné.
  PHPStan analyzuje jen `.php`; `.phpt` ani `.latte` ne. Na typech záleží v `tests/inc/*.php` a `gui/tests/inc/*.php`.
- **Testy:** `make test` v kořeni, `cd gui && make test`. Jednotlivý soubor: `vendor/bin/tester -p php -C tests/Donut/Neco.phpt`.
- **ŽÁDNÁ ASERCE SE NESMÍ OSLABIT ANI SMAZAT.** Tohle je nejdůležitější věta plánu; dostane 194 příležitostí se porušit. Aserce se přepisuje na **nový přesný text**, nikdy na volnější tvar:

  ```php
  // správně
  Assert::contains("Blocks directory '{$dir}' does not exist.", $err);
  // ŠPATNĚ — vypadá to jako překlad, ale je to ztráta pokrytí
  Assert::contains('does not exist', $err);
  ```

  Každý task na konci doloží: **počet `Assert::` v každém dotčeném souboru před a po (nesmí klesnout)** a **tabulku před → po pro každou dotčenou aserci**.
- **Task opraví každou aserci, kterou svou změnou shodí — ať leží v kterémkoli souboru.** Seznam v „Files" říká, kde se mění *kód*, ne kde smějí být opravené aserce. Popisky formulářů z prezentérů se vykreslují do HTML, takže je vidí i testy šablon; ty aserce opraví ten task, který je shodil. **Sada je zelená po každém tasku** — to je vlastnost, kvůli které je plán rozdělený takhle.
- **Nezavádí se lokalizace.** Žádné `gettext`, žádný katalog hlášek, žádný přepínač jazyka. Řetězce se přepisují na místě. Kdyby tě u 194 asercí napadlo „tohle by chtělo překladovou vrstvu" — spec ji vědomě nedělá.
- **Glosář je závazný** — celý je v sekci „Glosář" specifikace. Jádro: kámen→`block`, krok→`step`, klíč→`key`, obálka→`envelope`, profil→`profile`, adresář→`directory`, řetězec→`string`, pole→`array`, objekt→`object`, povinný→`required`, neznámý→`unknown`, výchozí→`default`, neexistuje→`does not exist`, jméno→`name`, vstup/výstup→`input`/`output`. **Kámen není `node`** — `node` v GUI znamená krok.
- **Komentáře se v plánu nevypisují.** Plán uvádí přesné anglické znění všech běhových řetězců, protože ty pinují aserce a drift v nich bolí. Komentáře se překládají na místě podle glosáře — jejich zdrojový text je v souboru a vypisovat 1 700 řádků do plánu by z něj udělalo nečitelný slepenec. **Není to díra v plánu, je to rozhodnutí.**
- **Fixtura psaná přímo v testu se překládá** (`'pozdrav'`, `'hlasite'`, `'prazdne'` a spol.). Většina nemá diakritiku, takže je grep nenajde — hledej očima.
- **Fixtura čtená z `docs/workflows/donut/` se nepřekládá.** Osm testů ji čte jako testovací data: `tests/Donut/Writer.roundTrip.phpt`, `tests/Donut/acceptance.negative.phpt`, `tests/Donut/acceptance.rewrite.phpt`, `gui/tests/StepMapper.phpt`, `gui/tests/StepTree.phpt`, `gui/tests/KeyMap.ValidatorContract.phpt`, `gui/tests/StepPath.parse.phpt`, `gui/tests/InputMapper.phpt`. Diakritika v nich, která pochází z fixtury, je v pořádku; komentáře a identifikátory se v nich překládají jako všude jinde.
- **Úplnost po každém tasku:** `grep -rP "[áčďéěíňóřšťúůýž]" <soubory tasku>` vrací prázdno (s výjimkou osmi souborů výš). Grep neodhalí češtinu bez diakritiky — každý task navíc jednou přečte své soubory očima.
- **Commit na konci každého tasku**, anglicky, v imperativu (`Translate parser messages to English`).
- Mutace na konci tasku **přidává vadu**, neodebírá správné chování. Při běhu kontroluj, **která** aserce spadla — pád na dřívější aserci znamená, že mutace zasáhla víc, než měla.

---

### Task 1: `src/Parser/` — hlášky parseru

Parser je nejnižší vrstva a nikoho nezná, takže je to nejlevnější místo, kde se ukáže, jestli glosář stačí. Čtyřicet hlášek, z nichž většina má tvar `{$location}: <co> musí být <typ>.`

**Files:**
- Modify: `src/Parser/BlockParser.php`, `src/Parser/JsonSource.php`, `src/Parser/WorkflowParser.php`, `src/Parser/ParseException.php` (jen komentáře)
- Test: `tests/Donut/BlockParser.file.phpt`, `tests/Donut/BlockParser.invalid.phpt`, `tests/Donut/BlockParser.valid.phpt`, `tests/Donut/WorkflowParser.file.phpt`, `tests/Donut/WorkflowParser.invalid.phpt`, `tests/Donut/WorkflowParser.valid.phpt`

**Interfaces:**
- Produces: anglické znění hlášek `ParseException`. Tvary `key 'x' is required and must be …`, `{$path} has no 'x' key.` a `File '{$path}' …` používají tasky 2–7; drž je doslova.

- [ ] **Step 1: Přepiš řetězce v `src/Parser/BlockParser.php`**

| řádek | česky | anglicky |
|---|---|---|
| 29 | `{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'.` | `{$path}: name '{$block->name}' does not match the file name '{$expected}'.` |
| 50 | `{$location}: klíč 'args' je povinný a musí být pole.` | `{$location}: key 'args' is required and must be an array.` |
| 57 | `{$location}: args[{$i}] musí být pole řetězců.` | `{$location}: args[{$i}] must be an array of strings.` |
| 64 | `{$location}: args[{$i}][{$j}] musí být řetězec.` | `{$location}: args[{$i}][{$j}] must be a string.` |
| 77 | `{$location}: klíč 'stdin' musí být objekt.` | `{$location}: key 'stdin' must be an object.` |
| 92 | `{$location}: 'timeout' musí být kladné celé číslo.` | `{$location}: 'timeout' must be a positive integer.` |
| 120 | `{$location}: klíč '{$key}' je povinný a musí být neprázdný řetězec.` | `{$location}: key '{$key}' is required and must be a non-empty string.` |

- [ ] **Step 2: Přepiš řetězce v `src/Parser/JsonSource.php`**

| řádek | česky | anglicky |
|---|---|---|
| 30 | `Soubor '{$path}' nejde přečíst.` | `File '{$path}' cannot be read.` |
| 37 | `Soubor '{$path}' není platný JSON: {$e->getMessage()}` | `File '{$path}' is not valid JSON: {$e->getMessage()}` |
| 41 | `Soubor '{$path}' musí obsahovat objekt.` | `File '{$path}' must contain an object.` |
| 65 | `neznámý klíč '{$key}'.` | `unknown key '{$key}'.` |
| 66 | `{$what} má neznámý klíč '{$key}'.` | `{$what} has an unknown key '{$key}'.` |
| 87 | `{$location}: klíč 'inputs' musí být objekt.` | `{$location}: key 'inputs' must be an object.` |
| 94 | `{$location}: jména vstupů musí být řetězce.` | `{$location}: input names must be strings.` |
| 98 | `{$location}: vstup '{$name}' musí být objekt.` | `{$location}: input '{$name}' must be an object.` |
| 130 | `{$location}: {$what} musí být řetězec.` | `{$location}: {$what} must be a string.` |
| 155 | `{$location}: {$what} jako pole musí obsahovat jen celá čísla.` | `{$location}: {$what} as an array must contain only integers.` |
| 166 | `{$location}: {$what} musí být true, false, nebo pole celých čísel.` | `{$location}: {$what} must be true, false, or an array of integers.` |

- [ ] **Step 3: Přepiš řetězce v `src/Parser/WorkflowParser.php`**

| řádek | česky | anglicky |
|---|---|---|
| 35 | `{$path}: name '{$workflow->name}' neodpovídá názvu souboru '{$expected}'.` | `{$path}: name '{$workflow->name}' does not match the file name '{$expected}'.` |
| 52 | `{$location}: klíč 'name' je povinný a musí být neprázdný řetězec.` | `{$location}: key 'name' is required and must be a non-empty string.` |
| 56 | `{$location}: klíč 'steps' je povinný a musí být pole.` | `{$location}: key 'steps' is required and must be an array.` |
| 79 | `{$location}: {$path}[{$i}] musí být objekt.` | `{$location}: {$path}[{$i}] must be an object.` |
| 98 | `{$location}: {$path} nemá klíč 'type'.` | `{$location}: {$path} has no 'type' key.` |
| 108 | `{$location}: {$path} má neznámý typ kroku '{$type}'.` | `{$location}: {$path} has an unknown step type '{$type}'.` |
| 127 | `{$location}: {$path} nemá klíč 'block'.` | `{$location}: {$path} has no 'block' key.` |
| 134 | `{$location}: {$path}.in musí být objekt řetězec => řetězec.` | `{$location}: {$path}.in must be an object of string => string.` |
| 144 | `{$location}: {$path}.out musí být objekt řetězec => řetězec.` | `{$location}: {$path}.out must be an object of string => string.` |
| 158 | `{$location}: {$path}.timeout musí být kladné celé číslo.` | `{$location}: {$path}.timeout must be a positive integer.` |
| 189 | `{$location}: {$path} nemá klíč 'condition'.` | `{$location}: {$path} has no 'condition' key.` |
| 197 | `{$location}: {$path}.condition nemá 'left'.` | `{$location}: {$path}.condition has no 'left'.` |
| 201 | `{$location}: {$path}.condition nemá 'op'.` | `{$location}: {$path}.condition has no 'op'.` |
| 205 | `{$location}: {$path} nemá klíč 'then'.` | `{$location}: {$path} has no 'then' key.` |
| 209 | `{$location}: {$path}.else musí být pole.` | `{$location}: {$path}.else must be an array.` |
| 216 | `{$location}: {$path}.condition.right musí být řetězec.` | `{$location}: {$path}.condition.right must be a string.` |
| 246 | `{$location}: {$path} nemá klíč 'key'.` | `{$location}: {$path} has no 'key' key.` |
| 250 | `{$location}: {$path} nemá klíč 'value'.` | `{$location}: {$path} has no 'value' key.` |
| 275 | `{$location}: {$path} nemá klíč 'over'.` | `{$location}: {$path} has no 'over' key.` |
| 279 | `{$location}: {$path} nemá klíč 'as'.` | `{$location}: {$path} has no 'as' key.` |
| 283 | `{$location}: {$path} nemá klíč 'steps'.` | `{$location}: {$path} has no 'steps' key.` |
| 307 | `{$location}: {$path}.{$key} musí být objekt.` | `{$location}: {$path}.{$key} must be an object.` |

Čísla řádků jsou orientační — po prvních úpravách se posunou. Řiď se českým textem, ne řádkem.

- [ ] **Step 4: Přelož komentáře ve všech čtyřech souborech**

Sedmnáct řádků. Podle glosáře, *proč* zůstává *proč*. `ParseException.php` má jen komentáře, jinak se nemění.

- [ ] **Step 5: Spočítej aserce před přepsáním testů**

```bash
for f in tests/Donut/BlockParser.*.phpt tests/Donut/WorkflowParser.*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```

Výstup si ulož do reportu. Po Stepu 7 musí sedět na kus.

- [ ] **Step 6: Pusť testy a podívej se, které aserce spadly**

Run: `vendor/bin/tester -p php -C tests/Donut/BlockParser.file.phpt tests/Donut/BlockParser.invalid.phpt tests/Donut/BlockParser.valid.phpt tests/Donut/WorkflowParser.file.phpt tests/Donut/WorkflowParser.invalid.phpt tests/Donut/WorkflowParser.valid.phpt`
Expected: FAIL, všechny na starém českém textu hlášky. Zapiš si které — je to seznam práce pro další krok.

- [ ] **Step 7: Přepiš aserce na nový text**

Každou aserci přepiš na **přesné nové znění z tabulek výš**. Nezkracuj, neměň `Assert::same` na `Assert::contains`, nesluč dvě aserce do jedné. Ve stejném průchodu přelož komentáře a české identifikátory v těchto testech.

- [ ] **Step 8: Ověř, že počet asercí neklesl**

```bash
for f in tests/Donut/BlockParser.*.phpt tests/Donut/WorkflowParser.*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```
Expected: čísla shodná se Stepem 5. Kdyby kleslo, aserce se ztratila — najdi ji a vrať.

- [ ] **Step 9: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS a `[OK] No errors`

- [ ] **Step 10: Grep na zbylou diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" src/Parser tests/Donut/BlockParser.*.phpt tests/Donut/WorkflowParser.*.phpt`
Expected: prázdno. Pak soubory jednou přečti očima kvůli češtině bez diakritiky.

- [ ] **Step 11: Mutace — ověř, že aserce opravdu drží celý text**

V `src/Parser/JsonSource.php` dočasně zaměň hlášku za volnější tvar:

```php
			throw new ParseException("File '{$path}' must contain an object."); // původní
			throw new ParseException("File '{$path}' is bad."); // MUTACE
```

Run: `vendor/bin/tester -p php -C tests/Donut/BlockParser.invalid.phpt`
Expected: FAIL na aserci o tom, že soubor musí obsahovat objekt. Kdyby test prošel, aserce se při přepisu oslabila — najdi ji a vrať jí celý text. Pak mutaci vrať a ověř PASS.

- [ ] **Step 12: Commit**

```bash
git add src/Parser tests/Donut/BlockParser.file.phpt tests/Donut/BlockParser.invalid.phpt tests/Donut/BlockParser.valid.phpt tests/Donut/WorkflowParser.file.phpt tests/Donut/WorkflowParser.invalid.phpt tests/Donut/WorkflowParser.valid.phpt
git commit -m "Translate parser messages and tests to English"
```

---

### Task 2: `src/Validator/` — hlášky validátoru

Validátor mluví k autorovi workflow, ne k jeho uživateli, takže hlášky jsou věty, ne typové kontroly. Pozor na `$what`: hodnoty `'podmínka'` a `'šablona'` se dosazují **doprostřed vět** (`{$what} čte klíč …`), takže se překládají taky.

**Files:**
- Modify: `src/Validator/BlockValidator.php`, `src/Validator/Validator.php`, `src/Validator/Result.php`, `src/Validator/KeyFlow.php` (jen komentáře), `src/Validator/Problem.php` (jen komentáře)
- Test: `tests/Donut/BlockValidator.phpt`, `tests/Donut/Validator.keyFlow.phpt`, `tests/Donut/Validator.keys.phpt`, `tests/Donut/Validator.location.phpt`, `tests/Donut/Validator.steps.phpt`, `tests/Donut/Result.setKeys.phpt`

**Interfaces:**
- Consumes: nic z tasku 1 (validátor hlášky parseru necituje).
- Produces: anglické hlášky validátoru. Task 7 (`gui/src/Presentation/`) je ukazuje v GUI a `gui/tests/KeyMap.ValidatorContract.phpt` na ně má kontraktní test — jestli tam něco spadne, patří to do tasku 6, ne sem.

- [ ] **Step 1: Přepiš `src/Validator/BlockValidator.php`**

| česky | anglicky |
|---|---|
| `{%STDIN%} použito v args kamene "{$block->name}"` | `{%STDIN%} used in args of block "{$block->name}"` |
| `kámen "{$block->name}" používá v args proměnnou "{$key}", kterou nedeklaruje` | `block "{$block->name}" uses variable "{$key}" in args without declaring it` |

- [ ] **Step 2: Přepiš `src/Validator/Result.php`**

| česky | anglicky |
|---|---|
| `setKeys() už bylo jednou zavoláno.` | `setKeys() has already been called.` |

- [ ] **Step 3: Přepiš `src/Validator/Validator.php`**

| česky | anglicky |
|---|---|
| `klíč "{$key}" se zapisuje a nikdy nečte` | `key "{$key}" is written and never read` |
| `vstup "{$key}" se nikde nepoužívá` | `input "{$key}" is never used` |
| `'podmínka'` (hodnota `$what`) | `'condition'` |
| `'šablona'` (hodnota `$what`) | `'template'` |
| `kámen "{$step->block}" neexistuje` | `block "{$step->block}" does not exist` |
| `kámen "{$block->name}" nesmí mít vstup jménem "stdin" — je to jméno kanálu` | `block "{$block->name}" must not have an input named "stdin" — that is a channel name` |
| `kámen "{$block->name}" nečte stdin, ale krok ho plní` | `block "{$block->name}" does not read stdin, but the step fills it` |
| `kámen "{$block->name}" nedeklaruje vstup "{$name}"` | `block "{$block->name}" does not declare input "{$name}"` |
| `povinný vstup "{$name}" kamene "{$block->name}" není naplněn` | `required input "{$name}" of block "{$block->name}" is not filled` |
| `kámen "{$block->name}" vyžaduje stdin, krok ho neplní` | `block "{$block->name}" requires stdin, the step does not fill it` |
| `neznámý kanál "{$channel}"` | `unknown channel "{$channel}"` |
| `neznámý operátor "{$condition->op}"` | `unknown operator "{$condition->op}"` |
| `operátor "{$condition->op}" vyžaduje 'right'` | `operator "{$condition->op}" requires 'right'` |
| `klíč "{$key}" není platné jméno` | `key "{$key}" is not a valid name` |
| `{$what} čte klíč "{$key}", který nemusí existovat` | `{$what} reads key "{$key}", which may not exist` |
| `{$what} čte klíč "{$key}", který vzniká jen v některých průchodech — nesmí se od něj odvíjet, které kroky poběží` | `{$what} reads key "{$key}", which is created only on some paths — it must not decide which steps run` |
| `{$what} čte klíč "{$key}", který v tomto místě nemohl vzniknout` | `{$what} reads key "{$key}", which cannot have been created at this point` |
| `{$what} čte klíč "{$key}", který žádný krok nezapisuje` | `{$what} reads key "{$key}", which no step writes` |

- [ ] **Step 4: Přelož komentáře v celém `src/Validator/`** — 41 řádků, včetně `KeyFlow.php` a `Problem.php`, které jinak nemají co měnit.

- [ ] **Step 5: Spočítej aserce**

```bash
for f in tests/Donut/BlockValidator.phpt tests/Donut/Validator.*.phpt tests/Donut/Result.setKeys.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```
Ulož do reportu.

- [ ] **Step 6: Pusť testy a zapiš, které aserce spadly**

Run: `vendor/bin/tester -p php -C tests/Donut/BlockValidator.phpt tests/Donut/Validator.keyFlow.phpt tests/Donut/Validator.keys.phpt tests/Donut/Validator.location.phpt tests/Donut/Validator.steps.phpt tests/Donut/Result.setKeys.phpt`
Expected: FAIL na starých českých textech.

- [ ] **Step 7: Přepiš aserce na přesné nové znění, přelož komentáře a české identifikátory v těchto testech**

- [ ] **Step 8: Ověř, že počet asercí neklesl** — stejný příkaz jako Step 5, stejná čísla.

- [ ] **Step 9: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS a `[OK] No errors`

- [ ] **Step 10: Grep na diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" src/Validator tests/Donut/BlockValidator.phpt tests/Donut/Validator.*.phpt tests/Donut/Result.setKeys.phpt`
Expected: prázdno. Pak přečti očima.

- [ ] **Step 11: Mutace**

Ve `Validator.php` dočasně zkrať hlášku o nečteném klíči:

```php
					"key \"{$key}\" is written" // MUTACE, původně: … is written and never read
```

Run: `vendor/bin/tester -p php -C tests/Donut/Validator.keys.phpt`
Expected: FAIL na aserci o zapisovaném a nečteném klíči. Kdyby prošel, aserce se oslabila. Pak mutaci vrať a ověř PASS.

- [ ] **Step 12: Commit**

```bash
git add src/Validator tests/Donut/BlockValidator.phpt tests/Donut/Validator.keyFlow.phpt tests/Donut/Validator.keys.phpt tests/Donut/Validator.location.phpt tests/Donut/Validator.steps.phpt tests/Donut/Result.setKeys.phpt
git commit -m "Translate validator messages and tests to English"
```

---

### Task 3: `src/Runner/` + `src/Format/` — hlášky běhu

`src/Format/` nemá žádný běhový řetězec, jen 14 komentářů — jde s runnerem, protože ho popisuje.

**Files:**
- Modify: `src/Runner/CommandLine.php`, `src/Runner/ConditionEvaluator.php`, `src/Runner/ConsoleReporter.php`, `src/Runner/Runner.php`, ostatní `src/Runner/*.php` a všechny `src/Format/*.php` (jen komentáře)
- Test: `tests/Donut/Runner.linear.phpt`, `tests/Donut/Runner.branching.phpt`, `tests/Donut/Runner.startFailure.phpt`, `tests/Donut/Runner.acceptance.phpt`, `tests/Donut/ConsoleReporter.phpt`, `tests/Donut/NetteProcessRunner.phpt`, `tests/Donut/CommandLine.phpt`, `tests/Donut/ConditionEvaluator.phpt`, `tests/Donut/acceptance.negative.phpt`, `tests/Donut/acceptance.rewrite.phpt`

**Interfaces:**
- Consumes: `Statická validace neprošla:` obaluje hlášky z tasku 2 — ty už jsou anglicky, tady se překládá jen obal.
- Produces: anglické hlášky běhu. `tests/Donut/output/Runner.*.expected` jsou **soubory s očekávaným výstupem** — musí se přepsat spolu s hláškami, jinak testy padnou na diffu.

- [ ] **Step 1: Přepiš `src/Runner/CommandLine.php`**

| česky | anglicky |
|---|---|
| `{$location}: povinný vstup "{$name}" kamene "{$block->name}" má prázdnou hodnotu.` | `{$location}: required input "{$name}" of block "{$block->name}" has an empty value.` |

- [ ] **Step 2: Přepiš `src/Runner/ConditionEvaluator.php`**

| česky | anglicky |
|---|---|
| `{$location}: neznámý operátor "{$op}".` | `{$location}: unknown operator "{$op}".` |
| `{$location}: operátor "{$op}" vyžaduje 'right'.` | `{$location}: operator "{$op}" requires 'right'.` |
| `{$location}: operátor "{$op}" potřebuje čísla, dostal "{$left}" a "{$right}".` | `{$location}: operator "{$op}" needs numbers, got "{$left}" and "{$right}".` |

- [ ] **Step 3: Přepiš `src/Runner/ConsoleReporter.php`**

| česky | anglicky |
|---|---|
| `varování: {$message}` | `warning: {$message}` |

Malé písmeno zůstává — je to prefix řádku, ne věta.

- [ ] **Step 4: Přepiš `src/Runner/Runner.php`**

| česky | anglicky |
|---|---|
| `Statická validace neprošla:\n{$messages}` | `Static validation failed:\n{$messages}` |
| `{$workflow->name}.json: povinný vstup "{$name}" nemá hodnotu.` | `{$workflow->name}.json: required input "{$name}" has no value.` |
| `{$workflow->name}.json: nejde zjistit aktuální pracovní adresář.` | `{$workflow->name}.json: cannot determine the current working directory.` |
| `{$at}: krok typu " . \get_debug_type($step) . " runner neumí.` | `{$at}: the runner does not handle a step of type " . \get_debug_type($step) . ".` |
| `{$at}: kámen "{$block->name}" překročil limit {$timeout} s.` | `{$at}: block "{$block->name}" exceeded the {$timeout} s limit.` |
| `{$at}: kámen "{$block->name}" nešel spustit — příkaz "{$commandLine->command}": {$e->getMessage()}` | `{$at}: block "{$block->name}" could not be started — command "{$commandLine->command}": {$e->getMessage()}` |
| `{$at}: neznámý kanál "{$channel}".` | `{$at}: unknown channel "{$channel}".` |
| `{$at}: kámen "{$block->name}" skončil s exit code {$result->exitCode}.` | `{$at}: block "{$block->name}" finished with exit code {$result->exitCode}.` |

- [ ] **Step 5: Přelož komentáře v `src/Runner/` a `src/Format/`** — 74 řádků.

`src/Runner/Runner.php` má komentář o klíči `CWD` a pracovním adresáři. Ten popisuje **platné** chování (`CWD` se z `getcwd()` bere schválně) — přelož ho, neruš.

- [ ] **Step 6: Spočítej aserce**

```bash
for f in tests/Donut/Runner.*.phpt tests/Donut/ConsoleReporter.phpt tests/Donut/NetteProcessRunner.phpt tests/Donut/CommandLine.phpt tests/Donut/ConditionEvaluator.phpt tests/Donut/acceptance.*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```

- [ ] **Step 7: Pusť testy a zapiš, co spadlo**

Run: `vendor/bin/tester -p php -C tests/Donut/`
Expected: FAIL v souborech výš. Testy `Runner.*` porovnávají proti `tests/Donut/output/Runner.*.expected` — tam pád vypadá jako diff celého výstupu, ne jako jedna aserce.

- [ ] **Step 8: Přepiš aserce, soubory `tests/Donut/output/*.expected`, komentáře a české identifikátory**

`output/Runner.linear.expected`, `output/Runner.branching.expected`, `output/Runner.startFailure.expected` a `output/Cli.Application.expected` obsahují vypsané hlášky — přepiš je na nové znění. `*.actual` jsou dočasné soubory z běhu, ty ignoruj.

**`acceptance.negative.phpt` a `acceptance.rewrite.phpt` čtou fixturu z `docs/workflows/donut/`** — česká data v nich jsou v pořádku a zůstávají. Překládají se jen komentáře, identifikátory a aserce o hláškách nástroje.

- [ ] **Step 9: Ověř, že počet asercí neklesl** — stejný příkaz jako Step 6.

- [ ] **Step 10: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS a `[OK] No errors`

- [ ] **Step 11: Grep na diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" src/Runner src/Format tests/Donut/Runner.*.phpt tests/Donut/ConsoleReporter.phpt tests/Donut/NetteProcessRunner.phpt tests/Donut/CommandLine.phpt tests/Donut/ConditionEvaluator.phpt tests/Donut/output/`
Expected: prázdno. `acceptance.*.phpt` v seznamu schválně nejsou — mají českou fixturu.

- [ ] **Step 12: Mutace**

V `Runner.php` dočasně zkrať hlášku o exit code:

```php
				"{$at}: block \"{$block->name}\" failed." // MUTACE
```

Run: `vendor/bin/tester -p php -C tests/Donut/Runner.linear.phpt`
Expected: FAIL na porovnání s `output/Runner.linear.expected`. Pak mutaci vrať a ověř PASS.

- [ ] **Step 13: Commit**

```bash
git add src/Runner src/Format tests/Donut
git commit -m "Translate runner messages and tests to English"
```

---

### Task 4: `src/Writer/` + kořen `src/` — zbytek jádra

Sem patří i `Profile` a `MissingDir` z minulého projektu.

**Files:**
- Modify: `src/Writer/BlockWriter.php`, `src/Writer/WorkflowWriter.php`, ostatní `src/Writer/*.php` (komentáře), `src/BlockRepository.php`, `src/MissingDir.php`, `src/MissingKeyException.php`, `src/Profile.php`, `src/Template.php`, `src/Exception.php` (komentáře)
- Test: `tests/Donut/BlockWriter.phpt`, `tests/Donut/WorkflowWriter.phpt`, `tests/Donut/Writer.roundTrip.phpt`, `tests/Donut/BlockRepository.phpt`, `tests/Donut/MissingDir.phpt`, `tests/Donut/Profile.phpt`, `tests/Donut/Exception.basic.phpt`, `tests/Donut/Template.parse.phpt`, `tests/Donut/Template.render.phpt`

**Interfaces:**
- Produces: `MissingDir::hint()` v anglickém znění — používá ho task 5 (CLI) i task 6 (GUI stores). Musí být hotové dřív než obojí.

- [ ] **Step 1: Přepiš `src/Writer/BlockWriter.php` a `src/Writer/WorkflowWriter.php`**

| česky | anglicky |
|---|---|
| `{$path}: name '{$block->name}' neodpovídá názvu souboru '{$expected}'.` | `{$path}: name '{$block->name}' does not match the file name '{$expected}'.` |
| `{$path}: name '{$workflow->name}' neodpovídá názvu souboru '{$expected}'.` | `{$path}: name '{$workflow->name}' does not match the file name '{$expected}'.` |
| `{$path}: data se nepodařilo zakódovat do JSON: {$e->getMessage()}` | `{$path}: data could not be encoded to JSON: {$e->getMessage()}` |
| `neznámý typ kroku ' . $step::class` | `unknown step type ' . $step::class` |

První dva tvary jsou schválně shodné s taskem 1 — parser a writer hlásí totéž.

- [ ] **Step 2: Přepiš kořenové soubory `src/`**

| soubor | česky | anglicky |
|---|---|---|
| `BlockRepository.php` | `Adresář s kameny '{$directory}' neexistuje.` | `Blocks directory '{$directory}' does not exist.` |
| `BlockRepository.php` | `Kámen '{$name}' neexistuje.` | `Block '{$name}' does not exist.` |
| `MissingKeyException.php` | `Klíč '{$key}' v mapě neexistuje.` | `Key '{$key}' does not exist in the map.` |
| `Profile.php` | `DONUT_PROFILE="{$name}": jméno profilu je jméno adresáře, ne cesta.` | `DONUT_PROFILE="{$name}": the profile name is a directory name, not a path.` |
| `Profile.php` | ` Na sadu jinde v souborovém systému udělej symlink.` | ` Symlink a set that lives elsewhere in the file system.` |
| `Profile.php` | `Nevím, kde hledat profily: prostředí nemá HOME ani XDG_CONFIG_HOME.` | `Don't know where to look for profiles: the environment has neither HOME nor XDG_CONFIG_HOME.` |
| `Profile.php` | ` Nastav DONUT_HOME na kořen profilů.` | ` Set DONUT_HOME to the profiles root.` |
| `Template.php` | `Šablonu '{$source}' se nepodařilo rozparsovat.` | `Template '{$source}' could not be parsed.` |

`Profile.phpt` má aserci `'%A%symlink%A%'` — slovo `symlink` v nové hlášce zůstává, takže **tahle aserce projde beze změny**. Nesahej na ni.

`src/MissingDir.php` má hlášku složenou z konkatenace, takže ji tabulka neunese — je celá tady:

```php
	public static function hint(string $directory): string
	{
		return 'Donut will not create it — run `mkdir -p ' . $directory . '`.';
	}
```

Zpětné apostrofy kolem příkazu zůstávají — je to rada, kterou má jít zkopírovat a spustit.

- [ ] **Step 3: Přelož komentáře** — 63 řádků napříč `src/Writer/` a kořenem `src/`.

- [ ] **Step 4: Spočítej aserce**

```bash
for f in tests/Donut/BlockWriter.phpt tests/Donut/WorkflowWriter.phpt tests/Donut/Writer.roundTrip.phpt tests/Donut/BlockRepository.phpt tests/Donut/MissingDir.phpt tests/Donut/Profile.phpt tests/Donut/Exception.basic.phpt tests/Donut/Template.*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```

- [ ] **Step 5: Pusť testy a zapiš, co spadlo**

Run: `vendor/bin/tester -p php -C tests/Donut/`
Expected: FAIL na starých textech, mimo jiné v `MissingDir.phpt` (celá hláška) a `BlockRepository.phpt`.

- [ ] **Step 6: Přepiš aserce, komentáře a české identifikátory**

`tests/Donut/MissingDir.phpt` tvrdí o **celé** hlášce:

```php
Assert::same(
	'Donut will not create it — run `mkdir -p /home/x/.config/donut/default/blocks`.',
	MissingDir::hint('/home/x/.config/donut/default/blocks'),
);
```

**`Writer.roundTrip.phpt` čte fixturu z `docs/workflows/donut/`** a na řádcích 97–98 testuje **neplatné UTF-8 schválně**. Ta bajtová fixtura se nesmí uklidit ani přeložit — přelož jen komentář nad ní.

- [ ] **Step 7: Ověř, že počet asercí neklesl** — stejný příkaz jako Step 4.

- [ ] **Step 8: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS a `[OK] No errors`

- [ ] **Step 9: Grep na diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" src/Writer src/*.php tests/Donut/BlockWriter.phpt tests/Donut/WorkflowWriter.phpt tests/Donut/BlockRepository.phpt tests/Donut/MissingDir.phpt tests/Donut/Profile.phpt tests/Donut/Exception.basic.phpt tests/Donut/Template.*.phpt`
Expected: prázdno. `Writer.roundTrip.phpt` v seznamu schválně není.

- [ ] **Step 10: Mutace**

V `MissingDir.php` dočasně zahoď `-p`:

```php
		return 'Donut will not create it — run `mkdir ' . $directory . '`.'; // MUTACE
```

Run: `vendor/bin/tester -p php -C tests/Donut/MissingDir.phpt`
Expected: FAIL. Pak mutaci vrať a ověř PASS.

- [ ] **Step 11: Commit**

```bash
git add src/Writer src/BlockRepository.php src/MissingDir.php src/MissingKeyException.php src/Profile.php src/Template.php src/Exception.php tests/Donut
git commit -m "Translate writer and core messages and tests to English"
```

---

### Task 5: `src/Cli/` + `bin/donut` — hlášky a nápověda CLI

Nápověda je jediný text, který uživatel uvidí **bez chyby**, takže je to nejviditelnější kus projektu. Pozor: `Chyba:` a `Profil:` **nemají diakritiku**, takže je závěrečný grep nenajde.

**Files:**
- Modify: `src/Cli/Application.php`, `src/Cli/Arguments.php`, `src/Cli/UsageException.php` (komentáře), `bin/donut` (komentáře)
- Test: `tests/Donut/Cli.Application.phpt`, `tests/Donut/Cli.Arguments.phpt`, `tests/Donut/Cli.acceptance.phpt`, `tests/Donut/output/Cli.Application.expected`

**Interfaces:**
- Consumes: `MissingDir::hint()` z tasku 4, hlášky parseru a validátoru z tasků 1–3 (CLI je obaluje prefixem).

- [ ] **Step 1: Přepiš prefixy a hlášky v `src/Cli/Application.php`**

| česky | anglicky |
|---|---|
| `Chyba: {$e->getMessage()}` (4×: ř. 75, 113, 118, 158) | `Error: {$e->getMessage()}` |
| `Vnitřní chyba nástroje: {$e->getMessage()}` | `Internal tool error: {$e->getMessage()}` |
| `Adresář s workflow '{$directory}' neexistuje. ` | `Workflows directory '{$directory}' does not exist. ` |
| `Adresář s kameny '{$blocksDir}' neexistuje. ` | `Blocks directory '{$blocksDir}' does not exist. ` |
| `Workflow "{$workflow->name}" nezná vstup "{$name}".` | `Workflow "{$workflow->name}" has no input "{$name}".` |
| `Workflow "{$name}" neexistuje. Hledal jsem v: {$directory}{$hint}` | `Workflow "{$name}" does not exist. Searched in: {$directory}{$hint}` |
| `'povinný'` / `'volitelný'` (v nápovědě workflow) | `'required'` / `'optional'` |

- [ ] **Step 2: Přepiš nápovědu v `printUsage()`**

```php
	private function printUsage(bool $isError): void
	{
		\fwrite($isError ? $this->stderr : $this->stdout, <<<TEXT
			donut --list                          list workflows
			donut <workflow> --help               help for a workflow
			donut <workflow> [--key=value …]      run

			Profile: {$this->profile->name()}  ({$this->profile->dir()})
			Another profile: DONUT_PROFILE=name, another root: DONUT_HOME=path

			TEXT);
	}
```

Sloupec s popisy je zarovnaný mezerami — po zkrácení textů zarovnání srovnej, ať to na terminálu drží.

- [ ] **Step 3: Přepiš `src/Cli/Arguments.php`**

| česky | anglicky |
|---|---|
| `Argument "{$arg}" nemá jméno klíče.` | `Argument "{$arg}" has no key name.` |
| `Argument --{$name} musí mít tvar --{$name}=hodnota.` | `Argument --{$name} must have the form --{$name}=value.` |
| `Argument --{$key} je příznak, nemá hodnotu.` | `Argument --{$key} is a flag, it takes no value.` |
| `Argument --{$key} je uvedený víckrát.` | `Argument --{$key} is given more than once.` |
| `Neznámý argument "{$arg}".` | `Unknown argument "{$arg}".` |
| `Workflow je uvedené víckrát: "{$workflow}" a "{$arg}".` | `Workflow is given more than once: "{$workflow}" and "{$arg}".` |

- [ ] **Step 4: Přelož komentáře v `src/Cli/` a `bin/donut`** — 33 řádků.

- [ ] **Step 5: Spočítej aserce**

```bash
for f in tests/Donut/Cli.Application.phpt tests/Donut/Cli.Arguments.phpt tests/Donut/Cli.acceptance.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done
```

- [ ] **Step 6: Pusť testy a zapiš, co spadlo**

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.Application.phpt tests/Donut/Cli.Arguments.phpt tests/Donut/Cli.acceptance.phpt`
Expected: FAIL na starých textech i na diffu proti `output/Cli.Application.expected`.

- [ ] **Step 7: Přepiš aserce, `output/Cli.Application.expected`, komentáře a české identifikátory**

V `Cli.Application.phpt` přejmenuj i pomocné jméno `spust()` na `run()` a proměnné `$prazdny` → `$empty`, `$hlaska` → `$message`. V `Cli.acceptance.phpt` `$jinde` → `$elsewhere`. **Fixturu psanou v testu** (`'pozdrav'`, `'hlasite'`) přelož taky — `'greet'`, `'loud'` — a nezapomeň, že se ta jména objevují i v asercích a v cestách k souborům fixtury.

Jméno profilu `testovaci` je fixtura psaná v testu, takže se překládá taky — na `testprofile`. Aserce se tím mění **dvakrát**, v popisku i v hodnotě:

```php
// bylo
Assert::contains('Profil: testovaci', $out);
// bude
Assert::contains('Profile: testprofile', $out);
```

Nezapomeň, že `testovaci` se v souboru objevuje i v konstruktoru `new Profile('testovaci', $dir)` — přejmenuj obojí, jinak aserce spadne.

- [ ] **Step 8: Ověř, že počet asercí neklesl** — stejný příkaz jako Step 5.

- [ ] **Step 9: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS a `[OK] No errors`

- [ ] **Step 10: Grep na diakritiku a ruční čtení**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" src/Cli bin tests/Donut/Cli.*.phpt tests/Donut/output/Cli.Application.expected`
Expected: prázdno.

**Pak povinně přečti `src/Cli/Application.php` a `src/Cli/Arguments.php` očima celé.** `Chyba:`, `Profil:`, `Neznamy` a spol. diakritiku nemají a grep je nechytí; tenhle task je jediný, kde jich je víc než jedna.

- [ ] **Step 11: Ověř nápovědu na skutečném běhu**

Run: `DONUT_HOME=$(mktemp -d) php bin/donut --help`
Expected: anglická nápověda se zarovnaným sloupcem popisů a řádkem `Profile: default  (…)`. Žádná česká slova.

- [ ] **Step 12: Mutace**

V `Arguments.php` dočasně zkrať hlášku:

```php
					throw new UsageException("Argument --{$name} is wrong."); // MUTACE
```

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.Arguments.phpt`
Expected: FAIL na aserci o tvaru `--klic=hodnota`. Pak mutaci vrať a ověř PASS.

- [ ] **Step 13: Commit**

```bash
git add src/Cli bin/donut tests/Donut/Cli.Application.phpt tests/Donut/Cli.Arguments.phpt tests/Donut/Cli.acceptance.phpt tests/Donut/output/Cli.Application.expected
git commit -m "Translate CLI messages, usage and tests to English"
```

---

### Task 6: `gui/src/*.php` — stores, mappery, stromy

Nejvíc komentářů z celého projektu (226) a jedna věc, která není překlad: **`StepCount::label()` má tři české tvary množného čísla, angličtina má dva.**

**Files:**
- Modify: `gui/src/BlockStore.php`, `gui/src/BlockUsage.php`, `gui/src/KeyMap.php`, `gui/src/StepCount.php`, `gui/src/StepMapper.php`, `gui/src/StepPath.php`, `gui/src/StepTree.php`, `gui/src/WorkflowRepository.php`, `gui/src/WorkflowStore.php`, a komentáře v `gui/src/BlockMapper.php`, `gui/src/FormFactory.php`, `gui/src/InputMapper.php`, `gui/src/ProblemMap.php`, `gui/src/RowShape.php`, `gui/src/StaticFile.php`, `gui/src/Text.php`, `gui/src/WorkflowMapper.php`, `gui/src/Bootstrap.php`
- Test: `gui/tests/BlockStore.phpt`, `gui/tests/BlockUsage.phpt`, `gui/tests/KeyMap.phpt`, `gui/tests/KeyMap.ValidatorContract.phpt`, `gui/tests/StepCount.phpt`, `gui/tests/StepMapper.phpt`, `gui/tests/StepPath.phpt`, `gui/tests/StepPath.parse.phpt`, `gui/tests/StepPath.ValidatorContract.phpt`, `gui/tests/StepTree.phpt`, `gui/tests/WorkflowRepository.phpt`, `gui/tests/WorkflowStore.phpt`, `gui/tests/BlockMapper.phpt`, `gui/tests/FormFactory.phpt`, `gui/tests/InputMapper.phpt`, `gui/tests/ProblemMap.phpt`, `gui/tests/RowShape.phpt`, `gui/tests/StaticFile.phpt`, `gui/tests/WorkflowMapper.phpt`

**Interfaces:**
- Consumes: `MissingDir::hint()` z tasku 4 (už anglicky) a hlášky validátoru z tasku 2 — `KeyMap.ValidatorContract.phpt` je na ně kontraktní test a **spadne, dokud task 2 nedoběhl**.
- Produces: `StepCount::label()` v anglickém tvaru — používají ho šablony v tasku 10.

- [ ] **Step 1: Přepiš hlášky o adresářích a kamenech**

| soubor | česky | anglicky |
|---|---|---|
| `BlockStore.php` | `Adresář s kameny '{$directory}' neexistuje. ` | `Blocks directory '{$directory}' does not exist. ` |
| `BlockStore.php` | `Kámen "{$name}" neexistuje. Hledal jsem v: {$this->directory}` | `Block "{$name}" does not exist. Searched in: {$this->directory}` |
| `WorkflowRepository.php` | `Adresář s workflow '{$directory}' neexistuje. ` | `Workflows directory '{$directory}' does not exist. ` |
| `WorkflowStore.php` | `Adresář s workflow '{$directory}' neexistuje. ` | `Workflows directory '{$directory}' does not exist. ` |

Znění je schválně shodné s taskem 4 a 5 — jádro, CLI i GUI hlásí chybějící adresář stejně.

- [ ] **Step 2: Přepiš hlášky o typech kroků a cestách**

| soubor | česky | anglicky |
|---|---|---|
| `BlockUsage.php`, `KeyMap.php` | `neznámý typ kroku ' . $step::class` | `unknown step type ' . $step::class` |
| `StepMapper.php` | `Neznámý typ kroku "' . self::text($values['type'] ?? '') . '".` | `Unknown step type "' . self::text($values['type'] ?? '') . '".` |
| `StepMapper.php` | `Neznámý typ kroku ' . $step::class . '.` | `Unknown step type ' . $step::class . '.` |
| `StepPath.php` | `"{$path}" není cesta ke kroku.` | `"{$path}" is not a step path.` |
| `StepTree.php` (2×) | `"{$at}" neukazuje na žádný krok.` | `"{$at}" does not point to any step.` |
| `StepTree.php` | `Pozice "{$at}" je mimo seznam kroků.` | `Position "{$at}" is outside the step list.` |
| `StepTree.php` (2×) | `Cesta "{$at}" sestupuje do "{$property}", které krok ' . $step::class . ' nemá.` | `Path "{$at}" descends into "{$property}", which step ' . $step::class . ' does not have.` |

- [ ] **Step 3: Přepiš `StepCount::label()` — tři tvary na dva**

Čeština rozlišuje 1 / 2–4 / 5+, angličtina jen jednotné a množné. `match` se tím zkrátí:

```php
	/**
	 * English plural: "N nested step" / "N nested steps".
	 */
	public static function label(int $n): string
	{
		$word = $n === 1 ? 'nested step' : 'nested steps';

		return "{$n} {$word}";
	}
```

Doc komentář nad metodou mluvil o českém tvaru — přepiš ho, jak je výš.

- [ ] **Step 4: Přelož komentáře v celém `gui/src/*.php`** — 226 řádků, největší dávka projektu. Nesahej na `gui/src/Presentation/` — ta je task 7.

- [ ] **Step 5: Spočítej aserce**

```bash
for f in gui/tests/*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done > /tmp/asserts-before.txt; cat /tmp/asserts-before.txt
```

Počítej **všechny** `gui/tests/*.phpt`, ne jen soubory tohoto tasku — stejný seznam použijí i tasky 7–10 a porovnání pak sedí napříč.

- [ ] **Step 6: Pusť testy a zapiš, co spadlo**

Run: `cd gui && make test`
Expected: FAIL na starých textech. Prezentérové testy zatím **nesahej** — jsou to tasky 7–10.

- [ ] **Step 7: Přepiš aserce, komentáře a české identifikátory v testech tohoto tasku**

**`StepCount.phpt` — pozor:** pět asercí na `label()` testuje 1, 2, 4, 5 a 17. Hranice 2–4 v angličtině mizí, ale **všech pět asercí zůstává**:

```php
Assert::same('1 nested step', StepCount::label(1));
Assert::same('2 nested steps', StepCount::label(2));
Assert::same('4 nested steps', StepCount::label(4));
Assert::same('5 nested steps', StepCount::label(5));
Assert::same('17 nested steps', StepCount::label(17));
```

Že tři z nich teď ověřují totéž pravidlo, není důvod je mazat — smazat aserci je rozhodnutí o pokrytí, ne překlad. Reviewer to nemá hlásit jako duplicitu.

**Čtyři testy tohoto tasku čtou fixturu z `docs/workflows/donut/`** — `StepMapper.phpt`, `StepTree.phpt`, `KeyMap.ValidatorContract.phpt`, `StepPath.parse.phpt`. Česká data v nich zůstávají; překládají se komentáře, identifikátory a aserce o hláškách nástroje.

- [ ] **Step 8: Ověř, že počet asercí neklesl**

```bash
for f in gui/tests/*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done | diff /tmp/asserts-before.txt -
```
Expected: žádný rozdíl.

- [ ] **Step 9: Obě sady a oba PHPStany**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech.

- [ ] **Step 10: Grep na diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" gui/src/*.php gui/tests/BlockStore.phpt gui/tests/BlockUsage.phpt gui/tests/KeyMap.phpt gui/tests/StepCount.phpt gui/tests/StepPath.phpt gui/tests/StepPath.ValidatorContract.phpt gui/tests/WorkflowRepository.phpt gui/tests/WorkflowStore.phpt gui/tests/BlockMapper.phpt gui/tests/FormFactory.phpt gui/tests/ProblemMap.phpt gui/tests/RowShape.phpt gui/tests/StaticFile.phpt gui/tests/WorkflowMapper.phpt`
Expected: prázdno. Čtyři testy s fixturou v seznamu schválně nejsou.

- [ ] **Step 11: Mutace**

V `BlockStore.php` dočasně zkrať hlášku:

```php
			throw new ParseException("Block \"{$name}\" does not exist."); // MUTACE, chybí „Searched in:"
```

Run: `cd gui && vendor/bin/tester -p php -C tests/BlockStore.phpt`
Expected: FAIL na aserci, která tvrdí o prohledané cestě. Pak mutaci vrať a ověř PASS.

- [ ] **Step 12: Commit**

```bash
git add gui/src/*.php gui/tests
git commit -m "Translate GUI store and mapper messages and tests to English"
```

---

### Task 7: `gui/src/Presentation/*.php` — popisky formulářů a hlášky prezentérů

Šedesát řetězců, z nichž většina jsou **popisky a validační hlášky formulářů** — to je text, který uživatel čte nejčastěji. `BlockPresenter` a `WorkflowPresenter` mají desítku popisků shodných; drž jim shodný i překlad.

**Files:**
- Modify: `gui/src/Presentation/Block/BlockPresenter.php`, `gui/src/Presentation/Workflow/WorkflowPresenter.php`, `gui/src/Presentation/Workflow/StepTreeControl.php`, `gui/src/Presentation/LayoutTemplate.php` a všechny `*Template.php` v `Block/` i `Workflow/` (jen komentáře)
- Test: `gui/tests/BlockPresenter.delete.phpt`, `gui/tests/BlockPresenter.deleteName.phpt`, `gui/tests/BlockPresenter.detail.phpt`, `gui/tests/WorkflowPresenter.broken.phpt`, `gui/tests/WorkflowPresenter.controls.phpt`, `gui/tests/WorkflowPresenter.headerForm.phpt`, `gui/tests/WorkflowPresenter.list.phpt`, `gui/tests/WorkflowPresenter.step.phpt`, `gui/tests/WorkflowPresenter.nameParameter.phpt`, `gui/tests/Rows.phpt`

**Interfaces:**
- Consumes: hlášky z tasků 2, 4 a 6 (prezentéry je chytají a ukazují).
- Produces: anglické popisky formulářů. Šablony v taskách 9 a 10 je vykreslují — jejich testy na ně tvrdí, takže tenhle task musí být dřív.

- [ ] **Step 1: Přepiš popisky sdílené oběma prezentéry**

Stejné znění v `BlockPresenter.php` i `WorkflowPresenter.php`:

| česky | anglicky |
|---|---|
| `'Jméno'` | `'Name'` |
| `'Jméno je povinné.'` | `'Name is required.'` |
| `'Jméno smí obsahovat jen písmena, číslice, pomlčku a podtržítko.'` | `'Name may contain only letters, digits, a hyphen and an underscore.'` |
| `'Povinný'` (aria-label) | `'Required'` |
| `'Výchozí'` (aria-label) | `'Default'` |
| `'Timeout musí být celé číslo.'` | `'Timeout must be an integer.'` |
| `'Timeout musí být kladný.'` | `'Timeout must be positive.'` |
| `'Povolené selhání'` | `'Allowed failure'` |
| `'jakýkoliv exit kód'` | `'any exit code'` |
| `'jen tyhle kódy:'` | `'only these codes:'` |
| `'Kódy zadej jako čísla oddělená čárkou, třeba 0, 1.'` | `'Enter codes as numbers separated by commas, for example 0, 1.'` |
| `'Uložit'` | `'Save'` |
| `'Není co mazat.'` | `'Nothing to delete.'` |

- [ ] **Step 2: Přepiš zbytek `gui/src/Presentation/Block/BlockPresenter.php`**

| česky | anglicky |
|---|---|
| `'Příkaz'` | `'Command'` |
| `'Příkaz je povinný.'` | `'Command is required.'` |
| `'Kámen čte stdin'` | `'Block reads stdin'` |
| `'stdin je povinný'` | `'stdin is required'` |
| `Kámen "{$block->name}" už existuje. Uprav ho, nebo zvol jiné jméno.` | `Block "{$block->name}" already exists. Edit it, or choose another name.` |
| `'Kámen se neuložil — oprav chyby níž.'` | `'The block was not saved — fix the errors below.'` |
| `Kámen "{$name}" nejde smazat — používá ho: ` | `Block "{$name}" cannot be deleted — used by: ` |

- [ ] **Step 3: Přepiš zbytek `gui/src/Presentation/Workflow/WorkflowPresenter.php`**

| česky | anglicky |
|---|---|
| `'Validace neproběhla: '` | `'Validation did not run: '` |
| `Cesta "{$at}" nepatří workflow "{$name}".` | `Path "{$at}" does not belong to workflow "{$name}".` |
| `'Název kroku'` | `'Step name'` |
| `'Kámen'` (popisek selectu) | `'Block'` |
| `'Vyber kámen.'` | `'Choose a block.'` |
| `'Pod jakým klíčem do mapy'` (aria-label) | `'Under which key in the map'` |
| `'převzít z kamene'` | `'inherit from the block'` |
| `'Klíč'` | `'Key'` |
| `'Klíč je povinný.'` | `'Key is required.'` |
| `'Operátor'` | `'Operator'` |
| `'Vyber operátor.'` | `'Choose an operator.'` |
| `'Přes co'` | `'Over what'` |
| `'Vyplň, přes co se iteruje.'` | `'Fill in what to iterate over.'` |
| `'Pod jakým jménem'` | `'Under which name'` |
| `'Vyplň jméno položky.'` | `'Fill in the item name.'` |
| `'Chybí cesta ke kroku.'` | `'Step path is missing.'` |
| `Workflow "{$workflow->name}" už existuje. Uprav ho, nebo zvol jiné jméno.` | `Workflow "{$workflow->name}" already exists. Edit it, or choose another name.` |

- [ ] **Step 4: Přepiš `gui/src/Presentation/Workflow/StepTreeControl.php`**

| česky | anglicky |
|---|---|
| `Cesta "{$at}" nepatří workflow "{$this->name}".` | `Path "{$at}" does not belong to workflow "{$this->name}".` |

- [ ] **Step 5: Přelož komentáře v `gui/src/Presentation/**/*.php`** — 191 řádků včetně všech `*Template.php`, které jinak nemají co měnit.

- [ ] **Step 6: Pusť testy a zapiš, co spadlo**

Run: `cd gui && make test`
Expected: FAIL. Popisky formulářů se vykreslují do HTML, takže spadnou i aserce v testech šablon — `Layout.phpt`, `BlockPresenter.default.phpt`, `BlockPresenter.edit.phpt`, `WorkflowPresenter.envelope.phpt`, `WorkflowPresenter.detailRender.phpt`, `WorkflowPresenter.renderDetail.phpt`. Zapiš si **každou** spadlou aserci.

- [ ] **Step 7: Oprav každou aserci, kterou tenhle task shodil — i mimo seznam souborů výš**

Pravidlo celého plánu: **task opraví každou aserci, kterou svou změnou shodí, ať leží v kterémkoli souboru.** Seznam v „Files" říká, kde se mění *kód*, ne kde smějí být opravené aserce.

Aserce v testech šablon, které padly na popiscích z prezentérů (`Uložit` → `Save`, `Jméno` → `Name`, …), se opravují **tady**. Aserce, které padnou až na statickém textu šablon, opraví tasky 8–10 — ty ale zatím nepadají, protože šablony se v tomhle tasku nemění.

Ve stejném průchodu přelož komentáře a české identifikátory v testech tohoto tasku.

- [ ] **Step 8: Ověř, že počet asercí neklesl**

```bash
for f in gui/tests/*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done | diff /tmp/asserts-before.txt -
```
Expected: žádný rozdíl.

- [ ] **Step 9: Obě sady a oba PHPStany**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech. **Sada musí být zelená** — kdyby nebyla, zbyla neopravená aserce, kterou tenhle task shodil.

- [ ] **Step 10: Grep na diakritiku**

Run: `grep -rP "[áčďéěíňóřšťúůýž]" gui/src/Presentation --include=*.php`
Expected: prázdno. Šablony (`.latte`) v tomhle grepu schválně nejsou — jsou to tasky 8–10.

- [ ] **Step 11: Mutace**

Ve `WorkflowPresenter.php` dočasně zaměň popisek:

```php
		$form->addText('name', 'Label'); // MUTACE, původně 'Step name'
```

Run: `cd gui && vendor/bin/tester -p php -C tests/WorkflowPresenter.step.phpt`
Expected: FAIL na aserci o popisku kroku. Pak mutaci vrať a ověř PASS.

- [ ] **Step 12: Commit**

```bash
git add gui/src/Presentation gui/tests
git commit -m "Translate presenter form labels and messages to English"
```

---

### Task 8: `@layout.latte` — rám, navigace, drobečky

Layout je sdílený, takže se dotkne **každého** testu, který renderuje stránku. Je malý schválně: ať se ta vlna projeví dřív, než přijdou velké šablony.

Většina jeho textů **nemá diakritiku** (`Kameny`, `Workflow`, `Profil:`), takže je grep nenajde. Tenhle task se nesmí spolehnout na grep.

**Files:**
- Modify: `gui/src/Presentation/@layout.latte`
- Test: `gui/tests/Layout.phpt` a **každá aserce, kterou tenhle task shodí** (viz Global Constraints)

**Interfaces:**
- Consumes: `LayoutTemplate::$profile` z minulého projektu — jméno profilu se nepřekládá, mění se jen popisek před ním.

- [ ] **Step 1: Přepiš texty v `@layout.latte`**

| řádek | česky | anglicky |
|---|---|---|
| 16 | `aria-label="Hlavní navigace"` | `aria-label="Main navigation"` |
| 19 | `aria-label="Zavřít"` | `aria-label="Close"` |
| 24 | `Profil: <code>{$profile}</code>` | `Profile: <code>{$profile}</code>` |
| 30 | `>Workflow</a>` (položka navigace) | `>Workflows</a>` |
| 33 | `>Kameny</a>` | `>Blocks</a>` |
| 40 | `aria-label="Otevřít navigaci"` | `aria-label="Open navigation"` |
| 42 | `aria-label="Drobečky"` | `aria-label="Breadcrumbs"` |

Odkaz `Donut` na ř. 18 a `{block title}Donut{/block}` na ř. 5 jsou jméno nástroje — nechat.

- [ ] **Step 2: Přelož komentáře v `@layout.latte`** — čtyři bloky `{* … *}`.

- [ ] **Step 3: Pusť GUI testy a zapiš, co spadlo**

Run: `cd gui && make test`
Expected: FAIL. `Layout.phpt` tvrdí o navigaci (`>Workflow</a>`, `>Kameny</a>`) a drobečcích; spadnou i další testy, které renderují celou stránku.

- [ ] **Step 4: Oprav každou spadlou aserci**

`Layout.phpt` má aserce jako `Assert::contains('>Workflow</a>', $html)` a `Assert::match('~<li class="breadcrumb-item active" aria-current=page>Workflow</li>~', $html)`. Přepiš je na nový text, **ne** na volnější regulární výraz.

Aserce `Assert::contains('<code>projekt</code>', $html)` z minulého projektu tvrdí o **jménu profilu**, ne o popisku — zůstává beze změny.

- [ ] **Step 5: Ověř, že počet asercí neklesl**

```bash
for f in gui/tests/*.phpt; do echo "$(grep -c 'Assert::' "$f")	$f"; done | diff /tmp/asserts-before.txt -
```

- [ ] **Step 6: Obě sady a oba PHPStany**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech.

- [ ] **Step 7: Ruční čtení**

Přečti `@layout.latte` celý očima. Grep na diakritiku tu nestačí — `Kameny`, `Workflow` ani `Profil:` diakritiku nemají.

- [ ] **Step 8: Mutace**

V `@layout.latte` dočasně vrať českou položku navigace:

```latte
					…n:href="Block:default">Kameny</a>  {* MUTACE, má být Blocks *}
```

Run: `cd gui && vendor/bin/tester -p php -C tests/Layout.phpt`
Expected: FAIL na aserci o položce navigace. Pak mutaci vrať a ověř PASS.

- [ ] **Step 9: Commit**

```bash
git add gui/src/Presentation/@layout.latte gui/tests
git commit -m "Translate layout navigation and breadcrumbs to English"
```

---

### Task 9: Block šablony

**Files:**
- Modify: `gui/src/Presentation/Block/default.latte`, `gui/src/Presentation/Block/detail.latte`, `gui/src/Presentation/Block/edit.latte`
- Test: `gui/tests/BlockPresenter.default.phpt`, `gui/tests/BlockPresenter.edit.phpt`, `gui/tests/BlockPresenter.detail.phpt` a **každá další aserce, kterou tenhle task shodí**

**Interfaces:**
- Consumes: popisky formulářů z tasku 7 (už anglicky) a `Profile`/`MissingDir` hlášky z tasků 4 a 6.

- [ ] **Step 1: Přepiš `Block/default.latte`**

| česky | anglicky |
|---|---|
| `{block title}Kameny — Donut{/block}` | `{block title}Blocks — Donut{/block}` |
| drobeček `Kameny` | `Blocks` |
| `<h1>Kameny</h1>` | `<h1>Blocks</h1>` |
| `+ nový kámen` | `+ new block` |
| `V adresáři <code>{$dir}</code> žádné kameny nejsou.` | `There are no blocks in <code>{$dir}</code>.` |
| `<th scope=col>Jméno</th>` | `<th scope=col>Name</th>` |
| `<th scope=col>Příkaz</th>` | `<th scope=col>Command</th>` |
| `<th scope=col>Používá</th>` | `<th scope=col>Used by</th>` |
| `aria-label="Upravit kámen {$name}"` | `aria-label="Edit block {$name}"` |
| `>upravit</a>` | `>edit</a>` |

- [ ] **Step 2: Přepiš `Block/detail.latte`**

| česky | anglicky |
|---|---|
| drobeček `Kameny` | `Blocks` |
| `>upravit</a>` | `>edit</a>` |
| `<p>příkaz: <code>…</code></p>` | `<p>command: <code>…</code></p>` |
| `<h2 n:if="$block->inputs">Vstupy</h2>` | `<h2 n:if="$block->inputs">Inputs</h2>` |
| `{$input->required ? 'povinný' : 'volitelný'}` (2×) | `{$input->required ? 'required' : 'optional'}` |
| `(výchozí <code>{$input->default}</code>)` | `(default <code>{$input->default}</code>)` |
| `čte <strong>stdin</strong> —` | `reads <strong>stdin</strong> —` |
| `'jakýkoliv exit kód'` | `'any exit code'` |
| `<h2 n:if="$usedBy">Používá</h2>` | `<h2 n:if="$usedBy">Used by</h2>` |

- [ ] **Step 3: Přepiš `Block/edit.latte`**

| česky | anglicky |
|---|---|
| `{$name ?? 'Nový kámen'}` (2×: title i `<h1>`) | `{$name ?? 'New block'}` |
| drobeček `Kameny` | `Blocks` |
| `{$name === null ? 'nový kámen' : 'úprava'}` | `{$name === null ? 'new block' : 'edit'}` |
| `Skupina, ve které se proměnná vyhodnotí na prázdno, vypadne celá.` | `A group where a variable evaluates to empty is dropped whole.` |
| `<div class="card-header">Vstupy</div>` | `<div class="card-header">Inputs</div>` |
| `<th scope=col>Jméno</th>` | `<th scope=col>Name</th>` |
| `<th scope=col>Povinný</th>` | `<th scope=col>Required</th>` |
| `<th scope=col>Výchozí</th>` | `<th scope=col>Default</th>` |
| `<span class=visually-hidden>Smazat řádek</span>` a `aria-label="Smazat řádek"` | `Delete row` |
| `Výchozí hodnota se použije, když ji volající nepředá.` | `The default value is used when the caller does not pass one.` |
| `Na příkazové řádce se vstup zadává jako <code>--jmeno=hodnota</code>.` | `On the command line an input is given as <code>--name=value</code>.` |
| `<div class="card-header">Ostatní</div>` | `<div class="card-header">Other</div>` |
| `<div class="card-header text-danger">Smazat</div>` | `<div class="card-header text-danger">Delete</div>` |
| `Nejde smazat — používá ho: {implode(', ', $usedBy)}.` | `Cannot be deleted — used by: {implode(', ', $usedBy)}.` |
| `confirm('Opravdu smazat tenhle kámen?')` | `confirm('Really delete this block?')` |
| `>Smazat</button>` | `>Delete</button>` |

- [ ] **Step 4: Dohledej zbylé viditelné texty**

Plán vypisuje texty, které jsou v šablonách vidět dnes. Kdyby po Stepech 1–3 zbyl v těchhle třech souborech **jakýkoli** český viditelný text, přelož ho podle glosáře — je to doplnění téhož pravidla, ne nová práce:

```bash
grep -nP "[áčďéěíňóřšťúůýž]" gui/src/Presentation/Block/*.latte
```

Pak soubory přečti očima kvůli češtině bez diakritiky.

- [ ] **Step 5: Přelož komentáře ve všech třech šablonách.**

- [ ] **Step 6: Pusť GUI testy a zapiš, co spadlo**

Run: `cd gui && make test`

- [ ] **Step 7: Oprav každou spadlou aserci**

`BlockPresenter.default.phpt` a `BlockPresenter.edit.phpt` mají aserce na `mkdir -p …` z minulého projektu — ty tvrdí o hlášce z `MissingDir`, která je anglicky už od tasku 4, takže **se nemění**. Nesahej na ně.

- [ ] **Step 8: Ověř, že počet asercí neklesl** — proti `/tmp/asserts-before.txt`.

- [ ] **Step 9: Obě sady a oba PHPStany**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech.

- [ ] **Step 10: Mutace**

V `Block/default.latte` dočasně vrať český nadpis sloupce:

```latte
				<th scope=col>Používá</th>  {* MUTACE, má být Used by *}
```

Run: `cd gui && vendor/bin/tester -p php -C tests/BlockPresenter.default.phpt`
Expected: FAIL na aserci o hlavičce tabulky. Kdyby prošel, na ten sloupec žádná aserce netvrdí — **napiš to do reportu**, aserci nedoplňuj (přidat pokrytí není překlad).

Pak mutaci vrať a ověř PASS.

- [ ] **Step 11: Commit**

```bash
git add gui/src/Presentation/Block gui/tests
git commit -m "Translate block templates to English"
```

---

### Task 10: Workflow šablony a závěrečné síto

Poslední task. Kromě šablon zametá celý repozitář.

Pozor na `steps.latte:63-64`: **druhé místo s českým trojným množným číslem** (`vstup` / `vstupy` / `vstupů`), tentokrát přímo v šabloně.

**Files:**
- Modify: `gui/src/Presentation/Workflow/default.latte`, `detail.latte`, `edit.latte`, `step.latte`, `steps.latte` (`stepTree.latte` češtinu nemá — zkontroluj a nech být)
- Test: `gui/tests/WorkflowPresenter.*.phpt` a **každá další aserce, kterou tenhle task shodí**

**Interfaces:**
- Consumes: `StepCount::label()` z tasku 6 (už anglicky) a popisky formulářů z tasku 7.

- [ ] **Step 1: Přepiš `Workflow/default.latte`**

| česky | anglicky |
|---|---|
| `{block title}Workflow — Donut{/block}` | `{block title}Workflows — Donut{/block}` |
| drobeček a `<h1>` `Workflow` | `Workflows` |
| `+ nové workflow` | `+ new workflow` |
| `V adresáři <code>{$dir}</code> žádná workflow nejsou.` | `There are no workflows in <code>{$dir}</code>.` |
| `<th scope=col>Jméno</th>` | `<th scope=col>Name</th>` |
| `aria-label="Upravit workflow {$name}"` | `aria-label="Edit workflow {$name}"` |
| `>upravit</a>` | `>edit</a>` |

- [ ] **Step 2: Přepiš `Workflow/detail.latte`**

| česky | anglicky |
|---|---|
| `{if $workflow === null}Chyba{else}…` | `{if $workflow === null}Error{else}…` |
| drobeček `Workflow` | `Workflows` |
| `upravit hlavičku` | `edit header` |
| `<h2 n:if="$workflow->inputs">Vstupy</h2>` | `<h2 n:if="$workflow->inputs">Inputs</h2>` |
| `{$input->required ? 'povinný' : 'volitelný'}` | `{$input->required ? 'required' : 'optional'}` |
| `(výchozí <code>{$input->default}</code>)` | `(default <code>{$input->default}</code>)` |
| `Vybraný klíč: <code>{$selectedKey}</code>` | `Selected key: <code>{$selectedKey}</code>` |
| `zrušit výběr` | `clear selection` |
| `— tento klíč se ve workflow nevyskytuje` | `— this key does not occur in the workflow` |

- [ ] **Step 3: Přepiš `Workflow/edit.latte`**

| česky | anglicky |
|---|---|
| `{$name ?? 'Nové workflow'}` (2×) | `{$name ?? 'New workflow'}` |
| drobeček `Workflow` | `Workflows` |
| `{$name === null ? 'nové workflow' : 'hlavička'}` | `{$name === null ? 'new workflow' : 'header'}` |
| `<th scope=col>Jméno</th>` | `<th scope=col>Name</th>` |
| `<th scope=col>Povinný</th>` | `<th scope=col>Required</th>` |
| `<th scope=col>Výchozí</th>` | `<th scope=col>Default</th>` |
| `Smazat řádek` (2×) | `Delete row` |
| `Výchozí hodnota se použije, když ji volající nepředá.` | `The default value is used when the caller does not pass one.` |
| `Na příkazové řádce se vstup zadává jako <code>--jmeno=hodnota</code>.` | `On the command line an input is given as <code>--name=value</code>.` |

- [ ] **Step 4: Přepiš `Workflow/step.latte`**

| česky | anglicky |
|---|---|
| `Operátory <code>empty</code> a <code>not_empty</code> pravou stranu ignorují.` | `Operators <code>empty</code> and <code>not_empty</code> ignore the right side.` |
| `<th scope=col>Hodnota nebo <code>{='{%klíč%}'}</code></th>` | `<th scope=col>Value or <code>{='{%key%}'}</code></th>` |
| `Smazat řádek` (4×) | `Delete row` |
| `<div class="card-header">Výstupy do mapy</div>` | `<div class="card-header">Outputs to the map</div>` |
| `<th scope=col>Pod jakým klíčem do mapy</th>` | `<th scope=col>Under which key in the map</th>` |
| `+ výstup` | `+ output` |
| `<div class="card-header">Ostatní</div>` | `<div class="card-header">Other</div>` |

Popisek sloupce musí být **shodný** s aria-labelem z tasku 7 (`'Under which key in the map'`) — je to táž věc dvakrát.

- [ ] **Step 5: Přepiš `Workflow/steps.latte` — včetně druhého množného čísla**

| česky | anglicky |
|---|---|
| `čte` | `reads` |
| `bez vstupů` | `no inputs` |
| `{$inputCount === 1 ? 'vstup' : ($inputCount < 5 ? 'vstupy' : 'vstupů')}` | `{$inputCount === 1 ? 'input' : 'inputs'}` |
| `Kámen nemá vstupy.` | `The block has no inputs.` |
| `aria-label="Posunout dolů"` | `aria-label="Move down"` |

Trojný tvar se scvrkne na dvojný, stejně jako `StepCount::label()` v tasku 6. Zkontroluj i sourozence `Posunout nahoru` — diakritiku nemá, takže ho grep nenajde.

- [ ] **Step 6: Dohledej zbylé viditelné texty a přelož komentáře**

```bash
grep -nP "[áčďéěíňóřšťúůýž]" gui/src/Presentation/Workflow/*.latte
```
Pak všech pět šablon přečti očima. `stepTree.latte` češtinu nemá — ověř to a nech ho být.

- [ ] **Step 7: Pusť GUI testy a oprav každou spadlou aserci**

Run: `cd gui && make test`

`WorkflowPresenter.envelope.phpt`, `detailRender.phpt` a `renderDetail.phpt` mají nejvíc asercí o vyrenderovaném textu. Aserce na `mkdir -p …` se nemění — ty tvrdí o hlášce z `MissingDir`, anglické od tasku 4.

- [ ] **Step 8: Ověř, že počet asercí neklesl** — proti `/tmp/asserts-before.txt`.

- [ ] **Step 9: Obě sady a oba PHPStany**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech.

- [ ] **Step 10: Závěrečné síto přes celý repozitář**

```bash
grep -rP "[áčďéěíňóřšťúůýž]" src gui/src bin tests gui/tests \
  --include=*.php --include=*.phpt --include=*.latte --include=*.js --include=*.neon
```

Expected: hity **jen** v osmi souborech s fixturou z `docs/workflows/donut/`:
`tests/Donut/Writer.roundTrip.phpt`, `tests/Donut/acceptance.negative.phpt`, `tests/Donut/acceptance.rewrite.phpt`, `gui/tests/StepMapper.phpt`, `gui/tests/StepTree.phpt`, `gui/tests/KeyMap.ValidatorContract.phpt`, `gui/tests/StepPath.parse.phpt`, `gui/tests/InputMapper.phpt`.

Cokoli jiného oprav. `docs/` v grepu schválně není — dokumentace zůstává česky.

- [ ] **Step 11: Ověř GUI ručně**

Run: `cd gui && make server`, v druhém terminálu `curl -s 'http://127.0.0.1:8000/?presenter=Workflow&action=default' | grep -oP '>[A-Za-z][^<]{2,}<' | head -20`
Expected: samá anglická slova, žádné `Kameny` ani `upravit`. Projdi i `?presenter=Block&action=default` a jednu editaci. Server pak zastav.

- [ ] **Step 12: Mutace**

Ve `steps.latte` dočasně vrať český tvar:

```latte
{$inputCount === 1 ? 'vstup' : 'vstupy'}  {* MUTACE *}
```

Run: `cd gui && vendor/bin/tester -p php -C tests/WorkflowPresenter.detailRender.phpt`
Expected: FAIL na aserci o počtu vstupů. Kdyby prošel, na ten text žádná aserce netvrdí — napiš to do reportu, aserci nedoplňuj.

Pak mutaci vrať a ověř PASS.

- [ ] **Step 13: Commit**

```bash
git add gui/src/Presentation/Workflow gui/tests
git commit -m "Translate workflow templates to English"
```
