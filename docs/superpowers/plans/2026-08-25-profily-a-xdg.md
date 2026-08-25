# Profily a XDG cesty — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kameny a workflow se přestanou hledat v pracovním adresáři a začnou se číst z profilu `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`, stejně pro CLI i GUI.

**Architecture:** Nová hodnota `Donut\Profile` v jádře je jediné místo, kde se cesta počítá; skládá se z pole prostředí, existenci adresářů neověřuje. CLI ji staví ve statickém `Application::main()`, GUI ji dostává jako službu z DI kontejneru a prezentéry konstruktorem. Statické `WorkflowRepository::projectDir()` nad `getcwd()` mizí a s ním i `chdir()` v testech GUI.

**Tech Stack:** PHP 8.1+ (jádro) / 8.3+ (`gui/`), nette/utils, nette/application 3.2, Latte 3, nette/tester, PHPStan level max.

**Spec:** `docs/superpowers/specs/2026-08-25-profily-a-xdg-design.md`

## Global Constraints

- **PHPStan level max** v obou balíčcích. Ověřuj `make phpstan` (kořen) a `cd gui && make phpstan`. Čtyři pasti, na kterých kód z plánů v tomhle projektu opakovaně padá:
  1. `(string) $mixed` je `cast.string` — použij `is_string()` guard nebo pomocnou metodu.
  2. `Donut\Format` typuje kolekce jako `array<int, Step>`, ne `list<Step>` — anotace `list<…>` neprojde.
  3. `$form::Filled` neprojde, `Form::Filled` ano.
  4. `$nullable?->prop ?? $default` je `nullsafe.neverNull` — rozděl do mezipro­měnné.
  PHPStan analyzuje jen `.php`; `.phpt` testy ne. Na typech záleží v `tests/inc/*.php`.
- **Testy:** `make test` v kořeni, `cd gui && make test`. Jednotlivý soubor: `vendor/bin/tester -p php -C tests/Donut/Neco.phpt`.
- **Žádná stávající aserce se nesmí oslabit ani smazat.** Když se mění text hlášky, aserce se přepíše na nový text, ne na volnější tvar.
- **Odsazení tabulátory**, komentáře česky, komentář vysvětluje *proč*, ne *co* — drž se stylu okolního kódu.
- **Commit na konci každého tasku**, česky, v tvaru jako předchozí commity (`Profily: …`).
- Mutace na konci tasku **přidává vadu**, neodebírá správné chování. Při běhu kontroluj, **která** aserce spadla — pád na dřívější aserci znamená, že mutace zasáhla víc, než měla.

---

### Task 1: `Donut\Profile`

**Files:**
- Create: `src/Profile.php`
- Test: `tests/Donut/Profile.phpt`

**Interfaces:**
- Consumes: `Donut\Exception` (`src/Exception.php`, základ výjimek balíčku).
- Produces: `Donut\Profile` s konstruktorem `__construct(string $name, string $dir)`, statickou `fromEnvironment(array $env): self` (hází `Donut\Exception`) a metodami `name(): string`, `dir(): string`, `blocksDir(): string`, `workflowsDir(): string`. Používají ho tasky 3, 4 a 5.

- [ ] **Step 1: Write the failing test**

Vytvoř `tests/Donut/Profile.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\Exception;
use Donut\Profile;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Výchozí prostředí: profil `default` pod ~/.config/donut.
$profile = Profile::fromEnvironment(['HOME' => '/home/x']);
Assert::same('default', $profile->name());
Assert::same('/home/x/.config/donut/default', $profile->dir());
Assert::same('/home/x/.config/donut/default/blocks', $profile->blocksDir());
Assert::same('/home/x/.config/donut/default/workflows', $profile->workflowsDir());

// XDG_CONFIG_HOME přebíjí HOME.
Assert::same(
	'/cfg/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => '/cfg'])->dir(),
);

// DONUT_HOME přebíjí obojí.
Assert::same(
	'/sady/default',
	Profile::fromEnvironment([
		'HOME' => '/home/x',
		'XDG_CONFIG_HOME' => '/cfg',
		'DONUT_HOME' => '/sady',
	])->dir(),
);

// DONUT_PROFILE vybírá podadresář a je to i jméno profilu.
$olw = Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'olw']);
Assert::same('olw', $olw->name());
Assert::same('/home/x/.config/donut/olw', $olw->dir());

// Prázdná hodnota je totéž co nenastavená — stejné pravidlo jako u vstupů
// workflow. Bez něj by `DONUT_PROFILE= donut …` hledalo v kořeni profilů.
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '', 'DONUT_HOME' => ''])->dir(),
);
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => ''])->dir(),
);

// Relativní DONUT_HOME se použije, jak je — žádné realpath() kouzlení.
Assert::same('sady/default', Profile::fromEnvironment(['DONUT_HOME' => 'sady'])->dir());

// Jméno profilu je jméno adresáře, ne cesta. Cesty jinam se dělají symlinkem.
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a/b']),
	Exception::class,
	'%A%symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '..']),
	Exception::class,
	'%A%symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a\\b']),
	Exception::class,
	'%A%symlink%A%',
);

// Prostředí, ze kterého se cesta nedá složit, musí říct, čím to spravit.
Assert::exception(
	fn() => Profile::fromEnvironment([]),
	Exception::class,
	'%A%DONUT_HOME%A%',
);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/tester -p php -C tests/Donut/Profile.phpt`
Expected: FAIL — `Class "Donut\Profile" not found`.

- [ ] **Step 3: Write minimal implementation**

Vytvoř `src/Profile.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;


/**
 * Kde leží kameny a workflow: `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`.
 *
 * Je to hodnota, ne přístup na disk — existenci adresářů neověřuje. Chybějící
 * adresář hlásí až repozitáře při čtení, aby hláška uměla říct, co se hledalo.
 */
final class Profile
{
	public function __construct(
		private readonly string $name,
		private readonly string $dir,
	) {
	}


	/**
	 * Prostředí bere jako pole, ne přes getenv() uvnitř — jinak by se celá
	 * tabulka okrajových případů nedala otestovat bez putenv().
	 *
	 * @param  array<string, string> $env
	 * @throws Exception prostředí, ze kterého se cesta nedá složit
	 */
	public static function fromEnvironment(array $env): self
	{
		$name = self::value($env, 'DONUT_PROFILE') ?? 'default';

		// Jméno profilu je jméno adresáře. Cesta v něm by znamenala druhý
		// způsob, jak říct „hledej jinde" — od toho je symlink a DONUT_HOME.
		if (\str_contains($name, '/') || \str_contains($name, '\\') || $name === '.' || $name === '..') {
			throw new Exception(
				"DONUT_PROFILE=\"{$name}\": jméno profilu je jméno adresáře, ne cesta."
				. ' Na sadu jinde v souborovém systému udělej symlink.'
			);
		}

		$root = self::value($env, 'DONUT_HOME') ?? self::defaultRoot($env);

		return new self($name, $root . '/' . $name);
	}


	public function name(): string
	{
		return $this->name;
	}


	public function dir(): string
	{
		return $this->dir;
	}


	public function blocksDir(): string
	{
		return $this->dir . '/blocks';
	}


	public function workflowsDir(): string
	{
		return $this->dir . '/workflows';
	}


	/**
	 * @param  array<string, string> $env
	 * @throws Exception
	 */
	private static function defaultRoot(array $env): string
	{
		$config = self::value($env, 'XDG_CONFIG_HOME');

		if ($config !== null) {
			return $config . '/donut';
		}

		$home = self::value($env, 'HOME');

		if ($home === null) {
			throw new Exception(
				'Nevím, kde hledat profily: prostředí nemá HOME ani XDG_CONFIG_HOME.'
				. ' Nastav DONUT_HOME na kořen profilů.'
			);
		}

		return $home . '/.config/donut';
	}


	/**
	 * Prázdná hodnota je totéž co nenastavená — stejné pravidlo, jaké má
	 * formát u nevyplněných vstupů.
	 *
	 * @param array<string, string> $env
	 */
	private static function value(array $env, string $key): ?string
	{
		$value = $env[$key] ?? '';

		return $value === '' ? null : $value;
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/tester -p php -C tests/Donut/Profile.phpt`
Expected: PASS

- [ ] **Step 5: Mutace — ověř, že aserce na prázdnou hodnotu není vakuová**

V `Profile::value()` dočasně přidej vadu „prázdná hodnota je hodnota":

```php
		$value = $env[$key] ?? '';

		return $value; // MUTACE
```

Run: `vendor/bin/tester -p php -C tests/Donut/Profile.phpt`
Expected: FAIL, a to na aserci s komentářem „Prázdná hodnota je totéž co nenastavená" — ne na žádné dřívější. Když spadne dřív, mutace zasáhla víc, než měla; oprav ji, ne test.

Pak mutaci vrať zpět a znovu ověř PASS.

- [ ] **Step 6: PHPStan**

Run: `make phpstan`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add src/Profile.php tests/Donut/Profile.phpt
git commit -m "Profily: Donut\\Profile skládá cestu k sadě z prostředí"
```

---

### Task 2: `Donut\MissingDir` — rada patří i CLI

Dnes je `MissingDir` v `Donut\Gui` a radí `mkdir blocks` (jen jméno adresáře) plus „nebo spusť server z adresáře projektu". Obojí po přechodu na profily přestává platit: adresář už nesouvisí s pracovním adresářem, takže rada musí být celá cesta, a CLI ji potřebuje stejně jako GUI.

**Files:**
- Create: `src/MissingDir.php`, `tests/Donut/MissingDir.phpt`
- Delete: `gui/src/MissingDir.php`
- Modify: `gui/src/BlockStore.php:41`, `gui/src/WorkflowStore.php:35`, `gui/src/WorkflowRepository.php:42` (doplnit `use Donut\MissingDir;`), `gui/src/Presentation/Block/BlockPresenter.php:14`, `gui/src/Presentation/Workflow/WorkflowPresenter.php:14` (přepsat import)
- Test (aserce na starý text hlášky, všechny spadnou, všechny se musí přepsat): `gui/tests/WorkflowRepository.phpt:55`, `gui/tests/BlockPresenter.default.phpt:59`, `gui/tests/BlockPresenter.edit.phpt:183`, `gui/tests/WorkflowPresenter.envelope.phpt:153`, `gui/tests/WorkflowPresenter.renderDetail.phpt:88`

**Interfaces:**
- Produces: `Donut\MissingDir::hint(string $directory): string` — používá ji task 3 (CLI) i dosavadní kód GUI.

- [ ] **Step 1: Write the failing test**

Vytvoř `tests/Donut/MissingDir.phpt`:

```php
<?php

declare(strict_types=1);

use Donut\MissingDir;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Rada musí být spustitelná tak, jak je: celá cesta a -p, protože chybět
// může i profil nad adresářem, ne jen adresář sám.
Assert::same(
	'Donut ho sám nezaloží — vytvoř ho příkazem `mkdir -p /home/x/.config/donut/default/blocks`.',
	MissingDir::hint('/home/x/.config/donut/default/blocks'),
);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/tester -p php -C tests/Donut/MissingDir.phpt`
Expected: FAIL — `Class "Donut\MissingDir" not found`.

- [ ] **Step 3: Write minimal implementation**

Vytvoř `src/MissingDir.php`:

```php
<?php

declare(strict_types=1);

namespace Donut;


/**
 * Co s chybějícím adresářem `workflows/` nebo `blocks/`.
 *
 * Donut adresáře **nezakládá**: mlčky sypat adresáře na disk je horší než
 * hláška. Hláška tedy musí říct, co udělat — jinak je čerstvý profil slepá
 * ulička. Celá cesta a `-p` proto, že chybět může i profil nad adresářem.
 */
final class MissingDir
{
	public static function hint(string $directory): string
	{
		return 'Donut ho sám nezaloží — vytvoř ho příkazem `mkdir -p ' . $directory . '`.';
	}
}
```

Smaž `gui/src/MissingDir.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/tester -p php -C tests/Donut/MissingDir.phpt`
Expected: PASS

- [ ] **Step 5: Přepoj GUI na třídu z jádra**

V `gui/src/BlockStore.php`, `gui/src/WorkflowStore.php` a `gui/src/WorkflowRepository.php` doplň mezi ostatní `use` řádky:

```php
use Donut\MissingDir;
```

(Byly ve stejném jmenném prostoru jako stará třída, takže import neměly.)

V `gui/src/Presentation/Block/BlockPresenter.php` a `gui/src/Presentation/Workflow/WorkflowPresenter.php` přepiš existující řádek `use Donut\Gui\MissingDir;` na `use Donut\MissingDir;` (pořadí `use` je abecední — `Donut\MissingDir` patří před `Donut\Parser\ParseException`).

- [ ] **Step 6: Run GUI tests to see exactly which assertions the new text breaks**

Run: `cd gui && make test`
Expected: FAIL v pěti souborech — `WorkflowRepository.phpt`, `BlockPresenter.default.phpt`, `BlockPresenter.edit.phpt`, `WorkflowPresenter.envelope.phpt`, `WorkflowPresenter.renderDetail.phpt`. Všechny na starém textu rady.

- [ ] **Step 7: Přepiš aserce na nový text**

`gui/tests/WorkflowRepository.phpt:55` — celá hláška:

```php
	"Adresář s workflow '{$dir}/chybi' neexistuje. Donut ho sám nezaloží — vytvoř ho příkazem `mkdir -p {$dir}/chybi`.",
```

`gui/tests/BlockPresenter.default.phpt:59`:

```php
Assert::contains('mkdir -p ' . $prazdny . '/blocks', $html);
```

`gui/tests/BlockPresenter.edit.phpt:183`:

```php
Assert::contains('mkdir -p ' . $bezAdresare . '/blocks', $html);
```

`gui/tests/WorkflowPresenter.envelope.phpt:153`:

```php
Assert::contains('mkdir -p ' . $bezAdresare . '/workflows', $html);
```

`gui/tests/WorkflowPresenter.renderDetail.phpt:88`:

```php
Assert::contains('mkdir -p ' . $dir . '/blocks', $presenter->template->error);
```

Komentář v `gui/tests/BlockPresenter.default.phpt:51` („ale ne že stačí jeden mkdir") nech být — pořád platí. `Assert::notContains('mkdir', …)` na řádku 113 v `renderDetail.phpt` taky zůstává beze změny.

- [ ] **Step 8: Run all tests**

Run: `make test && cd gui && make test`
Expected: PASS obojí.

- [ ] **Step 9: PHPStan**

Run: `make phpstan && cd gui && make phpstan`
Expected: `[OK] No errors` v obou.

- [ ] **Step 10: Commit**

```bash
git add src/MissingDir.php tests/Donut/MissingDir.phpt gui/src gui/tests
git rm gui/src/MissingDir.php
git commit -m "Profily: rada k chybějícímu adresáři je v jádře a radí celou cestu"
```

---

### Task 3: CLI čte z profilu

**Files:**
- Modify: `src/Cli/Application.php` (konstruktor, `main()`, `listWorkflows()`, `loadWorkflow()`, `workflowNames()`, `runWorkflow()`, `printUsage()`, doc komentář třídy)
- Modify: `bin/donut`
- Test: `tests/Donut/Cli.Application.phpt`, `tests/Donut/Cli.acceptance.phpt`

**Interfaces:**
- Consumes: `Donut\Profile` z tasku 1 (`fromEnvironment()`, `name()`, `dir()`, `blocksDir()`, `workflowsDir()`), `Donut\MissingDir::hint()` z tasku 2.
- Produces: `Donut\Cli\Application::__construct(Profile $profile, $stdout = null, $stderr = null, ?string $stdin = null, ?ProcessRunner $processes = null)` a `Donut\Cli\Application::main(array $argv, array $env, $stdout = null, $stderr = null): int`.

- [ ] **Step 1: Write the failing test**

V `tests/Donut/Cli.Application.phpt` přepiš pomocnou funkci `spust()` (řádky s `new Application($dir, …)`) tak, aby stavěla profil:

```php
function spust(string $dir, array $argv, ?ProcessRunner $processes = null): array
{
	$out = fopen('php://memory', 'r+');
	$err = fopen('php://memory', 'r+');
	$code = (new Application(new Profile('testovaci', $dir), $out, $err, '', $processes))->run($argv);
	rewind($out);
	rewind($err);
	$result = [$code, stream_get_contents($out), stream_get_contents($err)];
	fclose($out);
	fclose($err);

	return $result;
}
```

Doplň nahoře `use Donut\Profile;` (abecedně za `use Donut\Cli\Application;`).

Aserci u neexistujícího workflow uprav v komentáři — pracovní adresář už není hrana nástroje, profil ano; samotné aserce nech:

```php
// neexistující workflow je kód 2 a hláška řekne, kde se hledalo — profil je
// nejostřejší hrana nástroje a nejčastější příčina téhle chyby
```

Na konec souboru (před nic, jen přidat) dopiš nové případy:

```php
// --- nápověda říká, ze kterého profilu se čte ---
// Bez toho se „donut --list nic nevypisuje" nedá odladit: uživatel nevidí,
// kam se nástroj díval, a pracovní adresář mu to už neprozradí.
[$code, $out] = spust($dir, ['donut', '--help']);
Assert::same(0, $code);
Assert::contains('Profil: testovaci', $out);
Assert::contains($dir, $out);
Assert::contains('DONUT_PROFILE=', $out);
Assert::contains('DONUT_HOME=', $out);

// --- chybějící adresář workflows: --list není ticho, ale návod ---
// Prázdný výpis a chybějící profil vypadají na terminálu stejně. Čerstvá
// instalace je přesně ten případ, kdy rozdíl potřebuješ vidět.
$prazdny = TEMP_DIR . '/bez-profilu';
FileSystem::createDir($prazdny);

[$code, $out, $err] = spust($prazdny, ['donut', '--list']);
Assert::same(2, $code);
Assert::same('', $out);
Assert::contains('neexistuje', $err);
Assert::contains('mkdir -p ' . $prazdny . '/workflows', $err);

// --- a totéž při pokusu o spuštění workflow ---
[$code, , $err] = spust($prazdny, ['donut', 'cokoliv']);
Assert::same(2, $code);
Assert::contains('mkdir -p ' . $prazdny . '/workflows', $err);

// --- main() přeloží nemožné prostředí na kód 2, ne na fatal ---
// Jediný důvod, proč main() existuje: v bin/donut nesmí zůstat větev, která
// se nedá otestovat.
$out = fopen('php://memory', 'r+');
$err = fopen('php://memory', 'r+');
$code = Application::main(['donut', '--list'], [], $out, $err);
rewind($err);
$hlaska = stream_get_contents($err);
fclose($out);
fclose($err);

Assert::same(2, $code);
Assert::contains('DONUT_HOME', $hlaska);

// --- main() s použitelným prostředím doběhne do Application ---
$out = fopen('php://memory', 'r+');
$err = fopen('php://memory', 'r+');
$code = Application::main(
	['donut', '--list'],
	['DONUT_HOME' => dirname($dir), 'DONUT_PROFILE' => basename($dir)],
	$out,
	$err,
);
rewind($out);
$vypis = stream_get_contents($out);
fclose($out);
fclose($err);

Assert::same(0, $code);
Assert::contains('pozdrav', $vypis);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.Application.phpt`
Expected: FAIL — `Application::__construct()` dostává `Profile` místo `string`, resp. `main()` neexistuje.

- [ ] **Step 3: Write minimal implementation**

V `src/Cli/Application.php`:

Doplň importy (abecedně mezi stávající):

```php
use Donut\MissingDir;
use Donut\Profile;
```

Uprav doc komentář třídy:

```php
/**
 * Vstupní bod z příkazové řádky.
 *
 * Profil a streamy bere v konstruktoru, aby šla testovat bez skutečného
 * terminálu — stejný důvod, proč má Reporter rozhraní.
 */
```

Konstruktor: `private readonly string $directory` nahraď za `private readonly Profile $profile`.

Nad `run()` přidej statický vstupní bod:

```php
	/**
	 * Vstupní bod z bin/donut: složí profil z prostředí a chybu prostředí
	 * přeloží na návratový kód. Je to tady, a ne v bin/donut, protože ve
	 * skriptu, který se nedá spustit z testu, nesmí zůstat žádná větev.
	 *
	 * @param array<int, string>    $argv
	 * @param array<string, string> $env
	 * @param resource|null         $stdout
	 * @param resource|null         $stderr
	 */
	public static function main(array $argv, array $env, $stdout = null, $stderr = null): int
	{
		try {
			$profile = Profile::fromEnvironment($env);

		} catch (DonutException $e) {
			\fwrite($stderr ?? STDERR, "Chyba: {$e->getMessage()}\n");

			return self::NotStarted;
		}

		return (new self($profile, $stdout, $stderr))->run($argv);
	}
```

V `runWorkflow()`:

```php
		$blocks = new BlockRepository($this->profile->blocksDir());
```

V `listWorkflows()` přidej na začátek metody:

```php
	private function listWorkflows(): int
	{
		$directory = $this->profile->workflowsDir();

		// Prázdný výpis a chybějící adresář vypadají na terminálu stejně —
		// jako ticho. Rozdíl musí říct hláška, jinak je čerstvý profil slepá
		// ulička.
		if (!\is_dir($directory)) {
			\fwrite(
				$this->stderr,
				"Chyba: Adresář s workflow '{$directory}' neexistuje. " . MissingDir::hint($directory) . "\n"
			);

			return self::NotStarted;
		}

		$code = self::Success;
```

`loadWorkflow()` — lomítko na konci zůstává, aby hláška „Hledal jsem v:" ukazovala cestu jako dosud:

```php
	private function loadWorkflow(string $name): Workflow
	{
		$directory = $this->profile->workflowsDir() . '/';
		$path = $directory . $name . '.json';

		if (!\is_file($path)) {
			// Rada `mkdir -p` dává smysl jen u chybějícího adresáře, ne
			// u překlepu ve jméně workflow.
			$hint = \is_dir($directory) ? '' : ' ' . MissingDir::hint($this->profile->workflowsDir());

			throw new UsageException(
				"Workflow \"{$name}\" neexistuje. Hledal jsem v: {$directory}{$hint}"
			);
		}

		return (new WorkflowParser)->parseFile($path);
	}
```

`workflowNames()`:

```php
		$paths = \glob($this->profile->workflowsDir() . '/*.json');
```

`printUsage()` — z nowdoc se stává heredoc, protože se do textu doplňuje profil:

```php
	private function printUsage(bool $isError): void
	{
		\fwrite($isError ? $this->stderr : $this->stdout, <<<TEXT
			donut --list                          seznam workflow
			donut <workflow> --help               nápověda k workflow
			donut <workflow> [--klic=hodnota …]   spuštění

			Profil: {$this->profile->name()}  ({$this->profile->dir()})
			Jiný profil: DONUT_PROFILE=jmeno, jiný kořen: DONUT_HOME=cesta

			TEXT);
	}
```

- [ ] **Step 4: Zjednoduš `bin/donut`**

Poslední řádek `bin/donut`:

```php
exit(Donut\Cli\Application::main($argv, \getenv()));
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.Application.phpt`
Expected: PASS

- [ ] **Step 6: Přepiš přijímací test na prostředí**

`tests/Donut/Cli.acceptance.phpt` pouští `bin/donut` jako skutečný proces s pracovním adresářem fixtury. Nově se fixtura předává prostředím; pracovní adresář zůstává, protože z něj běží kroky workflow a bere se z něj klíč `CWD`.

Nahraď funkci `donut()`:

```php
/**
 * Definice se berou z profilu (DONUT_HOME/DONUT_PROFILE), pracovní adresář
 * zůstává fixtura — z něj běží kroky a z něj je klíč CWD.
 *
 * Prostředí se procesu předává celé, ne přidáním k zděděnému: PATH tam musí
 * být kvůli `php` i kvůli příkazům kroků (echo, tr).
 *
 * @return array{int, string, string}
 */
function donut(string $dir, string $bin, string $args): array
{
	// deskriptor 0 je připnutý schválně: donut si stdin čte, když to není
	// terminál. Bez toho by závisel na tom, co proces zdědil, a mohl by se
	// na čtení zaseknout.
	$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$env = [
		'PATH' => (string) getenv('PATH'),
		'DONUT_HOME' => dirname($dir),
		'DONUT_PROFILE' => basename($dir),
	];
	$process = proc_open("php {$bin} {$args}", $descriptors, $pipes, $dir, $env);
	Assert::type('resource', $process);
	fclose($pipes[0]);
	$out = (string) stream_get_contents($pipes[1]);
	$err = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [proc_close($process), $out, $err];
}
```

Na konec souboru, před `FileSystem::delete(TEMP_DIR);`, dopiš případ, který ověří, že profil opravdu rozhoduje — spuštění z úplně jiného adresáře najde totéž:

```php
// Pracovní adresář o definicích nerozhoduje: běh z /tmp najde workflow
// stejně, protože profil je v prostředí.
$jinde = TEMP_DIR . '/jinde';
FileSystem::createDir($jinde);

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = [
	'PATH' => (string) getenv('PATH'),
	'DONUT_HOME' => dirname($dir),
	'DONUT_PROFILE' => basename($dir),
];
$process = proc_open("php {$bin} --list", $descriptors, $pipes, $jinde, $env);
Assert::type('resource', $process);
fclose($pipes[0]);
$out = (string) stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);

Assert::same(0, proc_close($process));
Assert::contains('hlasite', $out);
```

- [ ] **Step 7: Run the acceptance test**

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.acceptance.phpt`
Expected: PASS

- [ ] **Step 8: Mutace — ověř, že přijímací test opravdu čte z profilu**

V `bin/donut` dočasně přidej vadu „profil se přebije pracovním adresářem": za složení profilu podstrč `getcwd()` jako kořen.

```php
exit(Donut\Cli\Application::main($argv, ['DONUT_HOME' => \dirname((string) \getcwd()), 'DONUT_PROFILE' => \basename((string) \getcwd())] + \getenv()));
```

Run: `vendor/bin/tester -p php -C tests/Donut/Cli.acceptance.phpt`
Expected: FAIL na posledním případu (běh z `$jinde`) — `hlasite` se nenajde. Když spadne dřív, mutace zasáhla víc, než měla.

Pak mutaci vrať zpět a znovu ověř PASS.

- [ ] **Step 9: Celá sada a PHPStan**

Run: `make test && make phpstan`
Expected: PASS, `[OK] No errors`

- [ ] **Step 10: Commit**

```bash
git add src/Cli/Application.php bin/donut tests/Donut/Cli.Application.phpt tests/Donut/Cli.acceptance.phpt
git commit -m "Profily: CLI čte kameny a workflow z profilu, ne z pracovního adresáře"
```

---

### Task 4: GUI čte z profilu

**Files:**
- Modify: `gui/config/common.neon` (služba `Donut\Profile`)
- Modify: `gui/src/WorkflowRepository.php:105-108` (smazat `projectDir()`)
- Modify: `gui/src/Presentation/Workflow/WorkflowPresenter.php` (konstruktor, `workflowDir()`, `blockDir()`, doc komentář třídy), `gui/src/Presentation/Block/BlockPresenter.php` (konstruktor, `store()`, `loadWorkflows()`, `renderDefault()`)
- Modify: `gui/Makefile`
- Test: `gui/tests/inc/workflowPresenter.php`, `gui/tests/inc/blockPresenter.php`, `gui/tests/WorkflowPresenter.renderDetail.phpt`, `gui/tests/WorkflowPresenter.detailRender.phpt`, `gui/tests/WorkflowPresenter.nameParameter.phpt`

**Interfaces:**
- Consumes: `Donut\Profile` z tasku 1.
- Produces: `BlockPresenter::__construct(Profile $profile)` a `WorkflowPresenter::__construct(Profile $profile)`; testovací továrny `createBlockPresenter(array $post, bool $sameOrigin, Profile $profile)` a `createWorkflowPresenter(array $post, bool $sameOrigin, Profile $profile)`. Jméno profilu ve fixturách je `basename($dir)` — používá ho task 5.

- [ ] **Step 1: Write the failing test**

V `gui/tests/inc/workflowPresenter.php`:

- doplň `use Donut\Profile;` mezi ostatní importy,
- přidej třetí parametr továrně a předej ho prezentéru:

```php
function createWorkflowPresenter(array $post, bool $sameOrigin, Profile $profile): WorkflowPresenter
{
```

Profil je povinný, ne volitelný s výchozí hodnotou: továrnu volá jen
`runWorkflowPresenterIn()` a mlčky podstrčená cesta by byla přesně ta
neviditelná vazba na prostředí, kterou tenhle projekt ruší.

```php
	$presenter = new WorkflowPresenter($profile);
```

- v `runWorkflowPresenterIn()` zahoď `chdir()` a podstrč profil; doc komentář přepiš:

```php
/**
 * Prezenter čte profil, ne pracovní adresář — fixtura se mu předává jako
 * Profile. Jméno profilu je jméno adresáře fixtury, aby se dalo tvrdit i o
 * tom, co GUI ukazuje v hlavičce.
 *
 * @param  array<string, mixed> $params
 * @param  array<string, mixed> $post
 * @param  bool                 $sameOrigin viz createWorkflowPresenter()
 * @return array{0: mixed, 1: string} odpověď a vyrenderované HTML ('' u redirectu)
 */
function runWorkflowPresenterIn(string $dir, array $params, array $post = [], bool $sameOrigin = true): array
{
	$presenter = createWorkflowPresenter($post, $sameOrigin, new Profile(\basename($dir), $dir));
	$request = new Request('Workflow', $post === [] ? 'GET' : 'POST', $params, $post);

	$response = null;
	Assert::noError(function () use ($presenter, $request, &$response) {
		$response = $presenter->run($request);
	});

	if (!$response instanceof TextResponse) {
		return [$response, ''];
	}

	$source = $response->getSource();
	Assert::type(Template::class, $source);

	// getSource() má návratový typ mixed — Assert::type() to ověří za
	// běhu, ale PHPStanu typ nezúží. Instanceof je tu jen kvůli tomu.
	if (!$source instanceof Template) {
		throw new \LogicException('nedosažitelné — Assert::type() by už selhalo');
	}

	return [$response, $source->renderToString()];
}
```

V `gui/tests/inc/blockPresenter.php` udělej totéž — až na jméno prezentéru a signálu je to stejný soubor, tak ho drž stejný. Doplň `use Donut\Profile;`, uprav továrnu:

```php
function createBlockPresenter(array $post, bool $sameOrigin, Profile $profile): BlockPresenter
{
```
```php
	$presenter = new BlockPresenter($profile);
```

a přepiš běhovou pomůcku:

```php
/**
 * Prezenter čte profil, ne pracovní adresář — fixtura se mu předává jako
 * Profile. Jméno profilu je jméno adresáře fixtury, aby se dalo tvrdit i o
 * tom, co GUI ukazuje v hlavičce.
 *
 * @param  array<string, mixed> $params
 * @param  array<string, mixed> $post
 * @param  bool                 $sameOrigin viz createBlockPresenter()
 * @return array{0: mixed, 1: string} odpověď a vyrenderované HTML ('' u redirectu)
 */
function runBlockPresenterIn(string $dir, array $params, array $post = [], bool $sameOrigin = true): array
{
	$presenter = createBlockPresenter($post, $sameOrigin, new Profile(\basename($dir), $dir));
	$request = new Request('Block', $post === [] ? 'GET' : 'POST', $params, $post);

	$response = null;
	Assert::noError(function () use ($presenter, $request, &$response) {
		$response = $presenter->run($request);
	});

	if (!$response instanceof TextResponse) {
		return [$response, ''];
	}

	$source = $response->getSource();
	Assert::type(Template::class, $source);

	// getSource() má návratový typ mixed — Assert::type() to ověří za
	// běhu, ale PHPStanu typ nezúží. Instanceof je tu jen kvůli tomu.
	if (!$source instanceof Template) {
		throw new \LogicException('nedosažitelné — Assert::type() by už selhalo');
	}

	return [$response, $source->renderToString()];
}
```

Ve třech `.phpt`, které si prezentéra staví samy, uprav lokální `createPresenter()` a zahoď `chdir()`:

`gui/tests/WorkflowPresenter.renderDetail.phpt` — `use Donut\Profile;` nahoru, dál:

```php
function createPresenter(Profile $profile): WorkflowPresenter
{
```
```php
	$presenter = new WorkflowPresenter($profile);
```
```php
/**
 * renderDetail() čte profil, fixtura se mu tedy předává jako Profile.
 */
function renderDetailIn(string $dir): WorkflowPresenter
{
	$presenter = createPresenter(new Profile(basename($dir), $dir));
	Assert::noError(fn() => $presenter->renderDetail('w'));

	return $presenter;
}
```

`gui/tests/WorkflowPresenter.detailRender.phpt` — stejná úprava `createPresenter()`, a `renderDetailIn()`:

```php
/**
 * renderDetail() čte profil, fixtura se mu tedy předává jako Profile —
 * referenční zátěž má reálné then/else/foreach větve, není potřeba stavět
 * vlastní.
 */
function renderDetailIn(string $dir, string $name, ?string $key = null): string
{
	$presenter = createPresenter(new Profile(basename($dir), $dir));
	$params = ['name' => $name] + ($key === null ? [] : ['key' => $key]);
	$request = new Request('Workflow', 'GET', ['action' => 'detail'] + $params);

	$response = null;
	Assert::noError(function () use ($presenter, $request, &$response) {
		$response = $presenter->run($request);
	});

	Assert::type(TextResponse::class, $response);
	$source = $response->getSource();
	Assert::type(Template::class, $source);

	return $source->renderToString();
}
```

`gui/tests/WorkflowPresenter.nameParameter.phpt` — stejná úprava `createPresenter()`; blok `$cwd = getcwd(); chdir($dir); try { … } finally { chdir($cwd); }` nahraď tělem bez `try`/`finally`, kde se profil předává:

```php
$profile = new Profile(basename($dir), $dir);

// Existující workflow se jménem bez lomítek se najde normálně.
$presenter = createPresenter($profile);
Assert::noError(fn() => $presenter->renderDetail('w'));
Assert::null($presenter->template->error);

// Pokus dostat se lomítky mimo workflows/ dostane stejnou hlášku jako
// neexistující workflow — ne obsah souboru mimo workflows/.
$presenter = createPresenter($profile);
Assert::noError(fn() => $presenter->renderDetail('../blocks/echo'));
Assert::type('string', $presenter->template->error);
Assert::contains('neexistuje', $presenter->template->error);
Assert::notContains('command', $presenter->template->error);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd gui && make test`
Expected: FAIL — `Donut\Gui\Presentation\Workflow\WorkflowPresenter::__construct()` nebere argument.

- [ ] **Step 3: Write minimal implementation**

`gui/src/WorkflowRepository.php` — smaž celou statickou metodu `projectDir()` (řádky 105–108) i její prázdný řádek navíc.

`gui/src/Presentation/Workflow/WorkflowPresenter.php`:

- import `use Donut\Gui\WorkflowRepository;` zůstává (repozitář se dál používá), přidej `use Donut\Profile;`,
- doc komentář třídy:

```php
/**
 * Workflow se čtou z profilu, stejně jako u CLI. Nic se necachuje — soubor
 * se čte při každém requestu.
 */
```

- konstruktor nad první vlastnost:

```php
	public function __construct(private readonly Profile $profile)
	{
	}
```

- oba pomocníky na konci třídy:

```php
	private function workflowDir(): string
	{
		return $this->profile->workflowsDir();
	}


	private function blockDir(): string
	{
		return $this->profile->blocksDir();
	}
```

`gui/src/Presentation/Block/BlockPresenter.php`:

- přidej `use Donut\Profile;`; import `use Donut\Gui\WorkflowRepository;` zůstává, `loadWorkflows()` repozitář dál používá,
- konstruktor:

```php
	public function __construct(private readonly Profile $profile)
	{
	}
```

- v `renderDefault()`: `$dir = $this->profile->blocksDir();`
- v `store()`: `return new BlockStore($this->profile->blocksDir());`
- v `loadWorkflows()`: `$repository = new WorkflowRepository($this->profile->workflowsDir());`

`gui/config/common.neon` — přidej do `services:` (nad router, s komentářem):

```neon
services:
	# Kde leží kameny a workflow. Prostředí se čte při startu serveru, jako
	# u CLI — GUI profily nepřepíná za běhu, přepnutí je restart s jinou
	# proměnnou.
	- Donut\Profile::fromEnvironment(::getenv())
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd gui && make test`
Expected: PASS

- [ ] **Step 5: Přepni Makefile na prostředí**

`gui/Makefile` — nahraď blok s `project` a recept `server`:

```make
# Kde GUI hledá kameny a workflow: $(home)/$(profile)/{blocks,workflows}.
# docs/workflows/ je de facto kořen profilů — donut/ v něm má blocks/
# i workflows/.
home = $(CURDIR)/../docs/workflows
profile = donut
port = 8000

# Docroot musí být www/ (odtud se vydávají assety) a router script je povinný:
# bez něj by vestavěný server hledal soubory podle URL.
docroot = $(CURDIR)/www

.PHONY: server test phpstan
server:
	@echo "GUI: http://127.0.0.1:$(port)  nad profilem  $(profile)  ($(home)/$(profile))"
	@DONUT_HOME=$(home) DONUT_PROFILE=$(profile) $(php_bin) -S 127.0.0.1:$(port) -t $(docroot) $(docroot)/index.php
```

- [ ] **Step 6: Ověř server ručně**

Run: `cd gui && make server` (v druhém terminálu `curl -s 'http://127.0.0.1:8000/?presenter=Workflow&action=default' | head -40`)
Expected: seznam workflow z `docs/workflows/donut/workflows/`. Server pak zastav.

- [ ] **Step 7: PHPStan**

Run: `cd gui && make phpstan`
Expected: `[OK] No errors`

- [ ] **Step 8: Mutace — ověř, že se opravdu čte profil**

V `BlockPresenter::store()` dočasně přidej vadu „kameny se hledají i vedle profilu":

```php
	private function store(): BlockStore
	{
		return new BlockStore(\dirname($this->profile->blocksDir()) . '/../blocks');
	}
```

Run: `cd gui && vendor/bin/tester -p php -C tests/BlockPresenter.edit.phpt`
Expected: FAIL na aserci o uloženém kameni. Pak mutaci vrať a ověř PASS.

- [ ] **Step 9: Commit**

```bash
git add gui/config/common.neon gui/src gui/tests gui/Makefile
git commit -m "Profily: GUI čte z profilu a testy nemění pracovní adresář"
```

---

### Task 5: Jméno profilu v hlavičce GUI

Bez toho GUI neřekne, kterou sadu edituješ — a to byl přesně problém pracovního adresáře.

**Files:**
- Create: `gui/src/Presentation/LayoutTemplate.php`
- Modify: `gui/src/Presentation/@layout.latte`
- Modify: `gui/src/Presentation/Block/BlockDefaultTemplate.php`, `BlockDetailTemplate.php`, `BlockEditTemplate.php`, `gui/src/Presentation/Workflow/WorkflowDefaultTemplate.php`, `WorkflowDetailTemplate.php`, `WorkflowEditTemplate.php`, `WorkflowStepTemplate.php` (dědit z `LayoutTemplate`)
- Modify: `gui/src/Presentation/Block/BlockPresenter.php`, `gui/src/Presentation/Workflow/WorkflowPresenter.php` (`beforeRender()`)
- Test: `gui/tests/Layout.phpt` (tvrdí o hlavičce a už staví obě sekce nad fixturou `TEMP_DIR . '/projekt'`)

**Interfaces:**
- Consumes: `Donut\Profile::name()` z tasku 1, konstruktory prezentérů z tasku 4, `runWorkflowPresenterIn()` / `runBlockPresenterIn()` z tasku 4 (jméno profilu = `basename($dir)`).
- Produces: `Donut\Gui\Presentation\LayoutTemplate` s `public string $profile = '';`.

- [ ] **Step 1: Write the failing test**

Na konec `gui/tests/Layout.phpt` (fixtura je `$dir = TEMP_DIR . '/projekt'`, takže jméno profilu je `projekt`; `$html` je přehled workflow a `$blockDefault` přehled kamenů — obojí už soubor má) dopiš:

```php
// jméno profilu v hlavičce: pracovní adresář o sadě nerozhoduje, takže je to
// jediné, z čeho uživatel pozná, co vlastně edituje. Musí být na obou
// sekcích — kterákoli může být první, kam se dostane.
Assert::contains('<code>projekt</code>', $html);
Assert::contains('<code>projekt</code>', $blockDefault);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd gui && vendor/bin/tester -p php -C tests/Layout.phpt`
Expected: FAIL na první z nových asercí — `<code>projekt</code>` v HTML není.

- [ ] **Step 3: Write minimal implementation**

Vytvoř `gui/src/Presentation/LayoutTemplate.php`:

```php
<?php

declare(strict_types=1);

namespace Donut\Gui\Presentation;

use Nette\Bridges\ApplicationLatte\Template;


/**
 * Společný předek šablon, které se kreslí do @layout.latte.
 *
 * Jméno profilu je jediné, z čeho uživatel pozná, kterou sadu edituje —
 * pracovní adresář mu to po přechodu na profily neřekne. Výchozí prázdná
 * hodnota je kvůli testům, které renderují metodu prezentéru napřímo,
 * tedy bez beforeRender().
 */
abstract class LayoutTemplate extends Template
{
	public string $profile = '';
}
```

V sedmi šablonách stránek nahraď `extends Template` za `extends LayoutTemplate` a import `use Nette\Bridges\ApplicationLatte\Template;` za `use Donut\Gui\Presentation\LayoutTemplate;`:

`Block/BlockDefaultTemplate.php`, `Block/BlockDetailTemplate.php`, `Block/BlockEditTemplate.php`, `Workflow/WorkflowDefaultTemplate.php`, `Workflow/WorkflowDetailTemplate.php`, `Workflow/WorkflowEditTemplate.php`, `Workflow/WorkflowStepTemplate.php`.

`Workflow/StepTreeTemplate.php` **nech být** — je to šablona komponenty, layout se do ní nekreslí.

V obou prezentérech přidej `beforeRender()` (v `BlockPresenter` nad `renderDefault()`, ve `WorkflowPresenter` taky nad první `render*` metodu):

```php
	protected function beforeRender(): void
	{
		/** @var LayoutTemplate $template */
		$template = $this->template;
		$template->profile = $this->profile->name();
	}
```

a do importů obou `use Donut\Gui\Presentation\LayoutTemplate;`.

V `gui/src/Presentation/@layout.latte` pod blok s odkazem „Donut" (za uzavírací `</div>` hlavičky navigace, před `<ul class="nav nav-pills flex-column">`) přidej:

```latte
			{* Kterou sadu právě edituju. Bez tohohle GUI o profilu mlčí a
			   splete se jen jednou — zato tiše. *}
			<div class="text-body-secondary small mb-3">Profil: <code>{$profile}</code></div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd gui && vendor/bin/tester -p php -C tests/Layout.phpt`
Expected: PASS

- [ ] **Step 5: Run all GUI tests**

Run: `cd gui && make test`
Expected: PASS. Kdyby spadla aserce, která tvrdí o celém HTML hlavičky, přepiš ji na nový tvar — neoslabuj ji.

- [ ] **Step 6: Mutace — ověř, že se ukazuje jméno profilu, ne konstanta**

V `beforeRender()` ve `WorkflowPresenter` dočasně přidej vadu „profil je vždycky default":

```php
		$template->profile = 'default';
```

Run: `cd gui && vendor/bin/tester -p php -C tests/Layout.phpt`
Expected: FAIL na aserci nad `$html` (ne nad `$blockDefault` a ne na žádné dřívější aserci o navigaci). Pak mutaci vrať a ověř PASS.

- [ ] **Step 7: PHPStan**

Run: `cd gui && make phpstan`
Expected: `[OK] No errors`

- [ ] **Step 8: Commit**

```bash
git add gui/src gui/tests
git commit -m "Profily: hlavička GUI ukazuje, kterou sadu edituješ"
```

---

### Task 6: Dokumentace

**Files:**
- Modify: `readme.md` (sekce Installation a Example)
- Modify: `docs/format-specifikace.md` (nadpisy sekcí 1 a 2)
- Modify: `docs/zadani.md` (tabulka „Klíčová rozhodnutí", seznam „Pořadí prací")

- [ ] **Step 1: readme.md — první spuštění místo pracovního adresáře**

V sekci `## Installation` za větu `Donut requires PHP 8.1 or later.` přidej:

````markdown
Donut reads blocks and workflows from a profile, not from the current
directory. Create the default one:

```
mkdir -p ~/.config/donut/default/{blocks,workflows}
```

`DONUT_PROFILE=name` picks another profile, `DONUT_HOME=path` another root
of profiles. To use a set that lives elsewhere — in a project repository,
say — symlink it in: `ln -s ~/projects/olw/donut ~/.config/donut/olw`.
````

V sekci `## Example` nahraď odstavec o pracovním adresáři a strom `myproject/`:

````markdown
Donut looks for `blocks/` and `workflows/` **in the profile**, by default
`~/.config/donut/default/`:

```
~/.config/donut/default/
	blocks/
		greet.json
	workflows/
		hello.json
```
````

Zbytek sekce (JSON kamene, JSON workflow, běh, `--list`, `--help`) zůstává. Zkontroluj `grep -n 'working directory' readme.md` — nesmí zbýt žádný výskyt.

- [ ] **Step 2: docs/format-specifikace.md — cesty jsou relativní k profilu**

Pod nadpis sekce 1 (`## 1. Stavební kámen …`) přidej jako první řádek textu:

```markdown
Cesty v nadpisech jsou relativní k profilu, tedy k
`$DONUT_HOME/$DONUT_PROFILE/` (ve výchozím stavu
`~/.config/donut/default/`).
```

Pod nadpis sekce 2 (`## 2. Workflow …`) přidej:

```markdown
Cesta je relativní k profilu, viz sekci 1.
```

- [ ] **Step 3: docs/zadani.md — rozhodnutí a stav prací**

Do tabulky „Klíčová rozhodnutí" přidej řádek:

```markdown
| Kde jsou definice | Profil `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`, výchozí `~/.config/donut/default`. Pracovní adresář nerozhoduje; jeho jediná role je klíč `CWD` a adresář, ve kterém běží kroky. |
```

Do seznamu „Pořadí prací" jako bod 6:

```markdown
6. ~~Profily a XDG cesty~~ — hotovo, viz
   `superpowers/specs/2026-08-25-profily-a-xdg-design.md`
```

- [ ] **Step 4: Ověř, že v repozitáři nezbyla stará tvrzení**

Run: `grep -rn "pracovní\(m\)\? adresář\|working directory\|projectDir" readme.md docs/*.md src gui/src | grep -v CWD`
Expected: zbývají jen zmínky, které se týkají běhu kroků a klíče `CWD` (`src/Runner/Runner.php`), a spec/plán v `docs/superpowers/`. Cokoliv jiného oprav.

- [ ] **Step 5: Celá sada naposledy**

Run: `make test && make phpstan && cd gui && make test && make phpstan`
Expected: PASS a `[OK] No errors` ve všech čtyřech.

- [ ] **Step 6: Commit**

```bash
git add readme.md docs/format-specifikace.md docs/zadani.md
git commit -m "Profily: dokumentace mluví o profilu, ne o pracovním adresáři"
```
