# Runner formátu donut — návrh

Datum: 2026-08-03

## Cíl

Spustit workflow: projít kroky, dosadit hodnoty z mapy, poskládat příkazovou
řádku, spustit proces, uložit výstup zpátky do mapy. Navazuje na hotový
parser a validátor; CLI a GUI přijdou později nad stejnými třídami.

Referenční pravda je `docs/format-specifikace.md` verze 0.3.

## Co runner nedělá

- Nepouští shell. `Nette\Utils\Process::runExecutable()`, nikdy `runCommand()`.
- Neuklízí po sobě soubory — engine žádné nevytváří, viz sekce 6 specifikace.
- Nezná CLI. Argumenty, `--list` a `--help` jsou další krok.
- Nevolá workflow z workflow. Odloženo v zadání.

## Jednotky

Tři nesou logiku bez jakéhokoliv procesu — tam je většina toho, co se dá
pokazit, a testuje se to bez spouštění:

| jednotka | zodpovědnost |
|---|---|
| `Runner\CommandLine` | `Block` + hodnoty vstupů → `command` a pole argumentů |
| `Runner\ConditionEvaluator` | `Condition` + mapa → bool |
| `Runner\Runner` | prochází kroky, drží mapu, řídí `if` a `foreach` |

Dvě jsou rozhraní se svou produkční implementací:

| rozhraní | implementace | proč rozhraní |
|---|---|---|
| `Runner\ProcessRunner` | `Runner\NetteProcessRunner` | aby šel `Runner` testovat bez spouštění příkazů |
| `Runner\Reporter` | `Runner\ConsoleReporter`, `Runner\NullReporter` | aby `Runner` nepsal na `STDERR` napřímo |

K tomu dvě drobnosti: `Runner\ProcessResult` (stdout, stderr, exit code)
a `Runner\RunFailedException`.

Víc implementací se za těmi rozhraními neplánuje — jsou tam kvůli
testovatelnosti, ne kvůli zaměnitelnosti.

`ProcessRunner` a `Reporter` jsou rozhraní jen proto, aby `Runner` šel
testovat bez spouštění příkazů a bez psaní na `STDERR`. Nic jiného za tím
není a víc implementací se neplánuje.

### Vstupní bod

```php
Runner::run(Workflow $workflow, array $initialMap): array
```

Vrací výslednou mapu. Mapa je obyčejné `array<string, string>` — pravidlo
„čtení neexistujícího klíče je tvrdá chyba" už vynucuje `Template::render()`,
takže vlastní třída by nepřidala nic než vrstvu.

## Chování za běhu

### Validace je uvnitř `run()`

Specifikace ji má jako záruku daného formátu, ne jako službu volajícího.
Kdyby ji měl volat kdokoliv jiný, může na ni zapomenout CLI i pozdější GUI.
Chyby běh nespustí; varování jdou do `Reporter`u a běh pokračuje.

### Skládání příkazové řádky

Skupina argumentů vypadne, když se v ní některá `{%VAR%}` vyhodnotí na
prázdno. Prázdná hodnota u vstupu s `required: true` je tvrdá chyba, ne
vypadnutí — jinak by povinný argument tiše zmizel.

Hodnota vstupu vzniká takto, v tomhle pořadí:

1. krok ji uvádí v `in` → dosadí se šablona proti mapě
2. neuvádí, ale kámen má `default` → použije se default
3. jinak zůstane nevyplněná

Rozhoduje se pak podle **výsledné hodnoty, ne podle toho, odkud přišla**.
Nevyplněný vstup a vstup vyhodnocený na prázdný řetězec jsou totéž: obojí
shodí skupiny, které ho obsahují. A obojí je tvrdá chyba u vstupu
s `required: true` — včetně případu, kdy prázdno přišlo z `default`.

`{%STDIN%}` se do argumentů nedosazuje nikdy; je to označení kanálu.
Validátor to hlídá dopředu.

### Prostředí a pracovní adresář

Potomci **dědí prostředí i pracovní adresář** runneru (`$env = null`,
`$directory = null`).

Vypadá to jako rozpor s větou „nic dalšího z prostředí neprochází" ze
sekce 3 specifikace, ale ta mluví o **mapě** — o tom, co smí číst šablony
ve workflow. To zůstává v platnosti. Potomci jsou něco jiného: `gh`
potřebuje `$HOME` a svůj token, `git` `$PATH` a `SSH_AUTH_SOCK`, `curl`
případně proxy proměnné. Kurátorovaný seznam by znamenal honit se za každou
další, kterou nějaký nástroj chce, a rozbíjet se tiše.

Kroky, které potřebují běžet jinde, dostávají cestu argumentem
(`--work-dir`), stejně jako v dnešním bashi.

### Kanály

| kanál | odkud |
|---|---|
| `result` | standardní výstup procesu, zachytává se vždy |
| `stderr` | chybový výstup — viz níže |
| `exit_code` | návratový kód jako text |

**Stdout se zachytává vždy**, protože je to `result`.

**Zachycené hodnoty se zbavují koncového odřádkování**, přesně jako
`$(...)` v shellu. Není to kosmetika: `jq -r '.id'` vrací `5f2abc\n`
a v `card-dev` se třináct zachycených hodnot lepí do URL nebo do
argumentu. Syrový výstup by rozbil každé workflow v přepisu. Vnitřní
odřádkování zůstává — u výstupu agenta nebo u seznamu karet pro `foreach`
je nosné.

**Stderr se řídí tím, jestli ho krok mapuje v `out`.** Když ano, zachytí se
do paměti a uloží do mapy. Když ne, teče živě na `STDERR` runneru.

Nejde obojí a je to záměr: kdo si stderr vyžádá do mapy, ten za něj
odpovídá. Kdo ne, ten ho vidí na obrazovce — což je chování dnešního bashe,
kde `olw-*` píšou průběh na stderr (`git push >&2`). Krok `agent` běží
klidně deset minut a bez toho by nebyl k rozeznání od zaseknutí.

Standardní vstup se plní z `in` pod klíčem `STDIN`; bez něj dostane proces
prázdný řetězec.

### Hlášení průběhu

Runner píše ke každému kroku jeden řádek přes `Reporter`:

```
steps[0]   zjistit vlastní ID v Trellu
steps[1]   jq
steps[15]  připravit workspace
  Cloning into '/home/honza/Work/repos/...'      ← stderr potomka
steps[17]  spustit agenta
```

Uvnitř `foreach` se přidává hodnota iterace:

```
steps[5].steps[2]           BOARD=INDEV-OSS
steps[5].steps[5].steps[0]  SHORT_ID=aB3xY
```

Cesta je **stejná notace, jakou používají hlášky validátoru**. Když
validace řekne `steps[7]` a runner spadne na `steps[7]`, je to bez
přemýšlení tentýž krok a tentýž řádek v JSON souboru. Průběžné číslování
by tuhle vazbu nemělo a `foreach` ho stejně znemožňuje — celkový počet
kroků není před během známý, protože závisí na tom, kolik karet vrátí API.

`ConsoleReporter` píše na `STDERR`, aby se to nemíchalo s výstupem
workflow. `NullReporter` je pro testy.

### Chyby

**Selhání kroku zastaví běh.** Nenulový exit code je selhání, pokud ho
nepovoluje `allow_failure` — `true` povoluje cokoliv, pole povoluje své
prvky, `false` jen nulu. Krok si nastavení kamene může přepsat.

Kanály se do mapy zapisují **i u povoleného selhání**. Právě proto
`allow_failure` existuje: `sync` povolí `jptq task` návratový kód 1
a rozhoduje se podle `exit_code` v `if`. Kdyby se při nenulovém kódu
nezapisovalo, nebylo by podle čeho.

**Timeout** se bere z kroku, jinak z kamene, jinak je 60 sekund podle
specifikace. Vypršení hodí `ProcessTimeoutException`, proces dostane SIGKILL.
`allow_failure` timeout **nepokrývá**: je to překročení limitu, ne
návratový kód, na kterém by se šlo dohodnout.

**Nespuštěný proces** je třetí případ: když `command` kamene na stroji
neexistuje, `Process` hodí `ProcessFailedException` ještě předtím, než
vznikne jakýkoliv exit code. Validátor to chytit nemůže — neví, co je na
`PATH`. Bez ošetření by uživatel dostal syrovou výjimku bez cesty ke kroku
a bez jména kamene, tedy netušil by, který z třiceti kroků to byl.

Všechny tři případy končí `RunFailedException` s cestou ke kroku a jménem
kamene. **Jméno příkazu je v hlášce jen u nespuštěného procesu** — tam je
příčinou, jinde by byl šum, protože kámen si ho drží ve svém JSON souboru.
**Stderr v hlášce není nikdy** — ten už uživatel viděl na obrazovce,
protože se streamoval.

Žádný rollback. Workflow spouští unixové příkazy, které si po sobě
neuklidí, a předstírat opak by bylo horší než to přiznat.

## Testy

**Jednotkové, bez procesů:** `CommandLine` a `ConditionEvaluator` jsou čisté
funkce. Sem patří všechny záludnosti — vypadávání skupin, prázdná hodnota
u povinného vstupu, `default`, číselné porovnání, nečíselná hodnota jako
chyba.

**`Runner` s falešným `ProcessRunner`:** řízení toku bez spouštění čehokoliv
— pořadí kroků, zápis kanálů do mapy, větvení `if`, iterace `foreach`,
zastavení na chybě, `allow_failure`. Falešná implementace zaznamenává, co
by spustila, takže testy tvrdí i to, jaká příkazová řádka vznikla.

**Integrační, nad skutečnými binárkami:** `echo`, `cat`, `/usr/bin/false`,
`head`. Shell je zakázaný, takže tyhle testy ověřují to, co fake ověřit
nemůže — že se proces opravdu spustí, že stdin doteče, že exit code sedí.

**Přijímací:** malé workflow proběhne celé, od `Workflow` objektu po
výslednou mapu. Přepis v `docs/workflows/donut/` se spustit nedá — volá
Trello API a `gh` — takže zůstává tím, čím je: zátěží pro validátor.

## Otevřené a vědomě odložené

- **Dry run.** Vypsat poskládané příkazové řádky bez spuštění by bylo užitečné
  právě proto, že vypadávání skupin je záludné. `CommandLine` je proto
  samostatná jednotka a doplnit to půjde bez zásahu do `Runner`u. Teď se to
  nedělá.
- **Paralelní běh větví**, `on_error: continue`, historie běhů. Odloženo
  v zadání.
