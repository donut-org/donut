# Profily a XDG cesty — návrh

Datum: 2026-08-25

## Cíl

Kameny a workflow přestanou žít v pracovním adresáři a přestěhují se do
profilu pod `~/.config/donut/`. CLI i GUI čtou totéž místo, určené dvěma
proměnnými prostředí.

Nahrazuje sekci „Kde hledá kameny a workflow" v
`2026-08-03-cli-design.md` a rozhodnutí „server běží v tom pracovním
adresáři, ze kterého ho někdo spustil" v `2026-08-05-gui-design.md`
(nově v repozitáři `donut-org/donut-ui`).

## Proč

Pracovní adresář jako zdroj definic znamená, že `donut card-dev` udělá
něco jiného podle toho, kde stojíš, a že prázdný adresář vypadá jako
projekt bez workflow. U nástroje, který se volá odkudkoli, to je past.
Ručně psané JSONy jsou konfigurace — patří tam, kde je člověk hledá,
zálohuje a verzuje.

## Pravidlo

```
$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}
```

| proměnná | význam | není-li nastavena |
|---|---|---|
| `DONUT_HOME` | kořen profilů | `$XDG_CONFIG_HOME/donut`, jinak `$HOME/.config/donut` |
| `DONUT_PROFILE` | jméno podadresáře | `default` |

Výchozí cesta je tedy `~/.config/donut/default/{blocks,workflows}`.

Ne `~/.local/donut`: XDG zná config, data, state a cache, a ručně psaná
deklarace patří do configu — stejná kategorie jako
`~/.config/systemd/user/*.service`.

Projektová sada se zapojí symlinkem, ne druhou cestou:

```
ln -s ~/projekty/olw/donut ~/.config/donut/olw
DONUT_PROFILE=olw donut nejake-workflow
```

### Okrajové případy

| situace | chování |
|---|---|
| `DONUT_PROFILE=""` | totéž co nenastaveno → `default`; sedí s pravidlem formátu „nevyplněno a `""` je totéž" |
| `DONUT_HOME=""` | totéž co nenastaveno → výchozí kořen, týmž pravidlem |
| `DONUT_PROFILE` obsahuje `/` nebo `..` | tvrdá chyba, konec — cesty jinam se dělají symlinkem |
| `HOME` i `XDG_CONFIG_HOME` chybí | tvrdá chyba s hláškou, ne hádání `/.config/donut` |
| `DONUT_HOME` relativní | použije se, jak je (vůči pracovnímu adresáři); žádné `realpath()` |
| profil nebo `blocks/`/`workflows/` chybí | hláška s přesným `mkdir -p …`; **nic se nezakládá samo** |

Nezakládání je rozhodnutí převzaté z `gui/src/MissingDir.php`: mlčky
sypat adresáře na disk je horší než hláška, která řekne, co udělat.

## Co se nemění

- **`CWD` v mapě enginu.** `Runner::prepareMap()` ho dál bere z
  `getcwd()`. Příkazy kroků běží v adresáři, odkud jsi donut spustil —
  to je po této změně jediná role pracovního adresáře a je to role
  správná: workflow pracuje s tvými soubory, ne se svými.
- Formát souborů, parser, validátor, runner.

## Co se vědomě nedělá

- **Přepínače `--profile` / `--home` na CLI.** `DONUT_PROFILE=olw donut x`
  je stejně dlouhé, funguje i pro GUI a nemusí se řešit v `Arguments`.
  Přidat se dají kdykoli, ubrat hůř.
- **Přepínač profilů v GUI.** GUI vyřeší cestu při startu stejně jako
  CLI a jméno profilu jen ukáže. Přepnutí = restart serveru s jinou
  proměnnou. Stav, který by musel přežít všechny formuláře a redirecty,
  za to nestojí.
- **Lokální `./blocks` a `./workflows`.** Ani jako fallback, ani jako
  překryv profilu. Dvě místa, odkud může kámen pocházet, znamenají
  otázku „odkud je tenhle?" v každé hlášce i v GUI.
- **`donut --init`.** Hláška s přesným `mkdir -p` příkazem stačí.

## Jednotky

| jednotka | zodpovědnost |
|---|---|
| `Donut\Profile` | prostředí → jméno profilu a cesty; jediné místo, kde se cesta počítá |
| `Donut\MissingDir` | rada, co udělat s chybějícím adresářem (přesun z `Donut\Gui`) |
| `Cli\Application::main()` | složí profil, chybu prostředí přeloží na kód 2 |
| `bin/donut` | jeden řádek |

### `Donut\Profile`

```php
final class Profile
{
	public function __construct(private readonly string $name, private readonly string $dir) {}
	public static function fromEnvironment(array $env): self;  // throws Donut\Exception
	public function name(): string;          // 'default'
	public function dir(): string;           // ~/.config/donut/default
	public function blocksDir(): string;     // …/blocks
	public function workflowsDir(): string;  // …/workflows
}
```

`fromEnvironment()` bere prostředí **jako pole**, ne přes `getenv()`
uvnitř. Celá tabulka okrajových případů pak jde otestovat bez
`putenv()`. Chyby jsou `Donut\Exception`, aby je volající chytil jedním
`catch` jako zbytek balíčku.

Konstruktor zůstává veřejný a bere hotové jméno a cestu — tudy si ho
podstrčí testy GUI i CLI.

`Profile` **nekontroluje, jestli adresáře existují**. Je to hodnota, ne
přístup na disk; chybějící adresář hlásí až repozitáře při čtení, přesně
jako dnes. Nové je jen to, že se k jejich hlášce přilepí rada
z `MissingDir`.

### CLI

`bin/donut` se smrskne na:

```php
exit(Donut\Cli\Application::main($argv, \getenv()));
```

Nové `Application::main(array $argv, array $env, $stdout = null, $stderr = null): int`
složí profil, chybu prostředí vypíše na stderr a vrátí `NotStarted` (2).
Důvod je tentýž, proč `Application` bere streamy v konstruktoru: v
`bin/donut` nesmí zůstat netestovatelná větev.

Konstruktor `Application` bere místo `string $directory` objekt
`Profile` — do hlášek potřebuje i jméno profilu.

Nápověda přestane lhát:

```
donut --list                          seznam workflow
donut <workflow> --help               nápověda k workflow
donut <workflow> [--klic=hodnota …]   spuštění

Profil: default  (/home/honza/.config/donut/default)
Jiný profil: DONUT_PROFILE=jmeno, jiný kořen: DONUT_HOME=cesta
```

Hláška o neexistujícím workflow ukazuje cestu už dnes; nově k ní u
chybějícího adresáře přibude rada z `MissingDir`.

### GUI

`WorkflowRepository::projectDir()` — statika nad `getcwd()` — mizí.
`Donut\Profile` se registruje jako služba v `gui/config/common.neon`:

```neon
services:
	- Donut\Profile::fromEnvironment(::getenv())
```

Kdyby neon volání funkce v argumentu nepodporoval, zastoupí ho
jednořádková továrna v `gui/src/`; na návrhu to nic nemění.

`BlockPresenter` a `WorkflowPresenter` si `Profile` berou konstruktorem,
jejich `blockDir()`/`workflowDir()` jen delegují. Statika mizí i z testů:
`runWorkflowPresenterIn()` a `runBlockPresenterIn()` si drží podpis, ale
místo `chdir()` podstrčí `new Profile('test', $dir)`.

`MissingDir::hint()` se přestěhuje do jádra a přeformuluje: místo
`mkdir <basename>` a rady „spusť server z adresáře projektu" (ta
přestala platit) řekne celý `mkdir -p <cesta>`.

`gui/Makefile` ztratí `cd` do projektu:

```make
home = $(CURDIR)/../docs/workflows
profile = donut

server:
	@DONUT_HOME=$(home) DONUT_PROFILE=$(profile) $(php_bin) -S 127.0.0.1:$(port) -t $(docroot) $(docroot)/index.php
```

`docs/workflows/` je dnes de facto kořen profilů: `donut/` už má
`blocks/` i `workflows/`. Absolutní `docroot` může zůstat, i když ho po
zmizení `cd` nic nevyžaduje.

## Testy

| test | co ověřuje |
|---|---|
| `tests/Donut/Profile.phpt` | celá tabulka okrajových případů nad polem prostředí |
| `tests/Donut/Cli.Application.phpt` | nápověda ukazuje jméno profilu a cestu; chybějící profil končí kódem 2 a radou |
| `tests/Donut/Cli.acceptance.phpt` | běh nad fixturou přes `new Profile(…)` místo adresáře |
| `gui/tests/…` | tři `.phpt` a dvě `inc/` továrny přestanou `chdir()`ovat |

Nový test na `Application::main()` s prostředím bez `HOME` — ta větev je
jediný důvod, proč `main()` existuje.

## Dokumentace

- `readme.md` — sekce Example přestane mluvit o pracovním adresáři,
  přibude první spuštění (`mkdir -p ~/.config/donut/default/{blocks,workflows}`)
  a obě proměnné.
- `docs/format-specifikace.md` — nadpisy `blocks/<jmeno>.json` a
  `workflows/<jmeno>.json` dostanou větu, že cesty jsou relativní
  k profilu.
- `docs/zadani.md` — řádek do „Klíčová rozhodnutí" a bod do „Pořadí
  prací".

## Migrace

Existující sada se zapojí symlinkem:

```
ln -s ~/Dokumenty/Projekty/donut-org/donut/docs/workflows/donut ~/.config/donut/donut
```

`olw/` a `jpw/` jsou pořád v bashovém rozvržení, netýká se jich to. Žádný
bashový skript v `docs/workflows/` dnes `donut <workflow>` nevolá, takže
mimo tento repozitář není co přepisovat.
