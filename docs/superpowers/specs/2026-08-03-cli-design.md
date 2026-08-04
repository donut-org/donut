# CLI wrapper pro donut — návrh

Datum: 2026-08-03

## Cíl

Spustit workflow z příkazové řádky, vypsat seznam workflow a nápovědu
k jednomu z nich. Navazuje na hotový parser, validátor a runner; GUI přijde
později nad týmiž třídami.

Referenční pravda je `docs/format-specifikace.md` verze 0.3, sekce 4.

## Co CLI nedělá

- Neparsuje JSON ani nevaliduje — to dělají vrstvy pod ním.
- Neskládá počáteční mapu. Defaulty vstupů, kontrolu povinných a `CWD`
  doplňuje `Runner::run()`; CLI jen předá, co přišlo z příkazové řádky.
- Nezná contributte ani žádný hotový console framework. Vlastní wrapper
  nad `$argv`, jak říká zadání.

## Kde hledá kameny a workflow

V **pracovním adresáři**: `./blocks/` a `./workflows/`.

Důsledek, který je potřeba znát: `sync` zařazuje do fronty příkazy, které
`jptq` spouští později. **`jptq consume` se proto musí spouštět z adresáře,
kde ty dva podadresáře jsou** — jinak workflow nenajde své kameny a projeví
se to až při konzumaci fronty, ne při zařazení. `jpw-queue-consume` zůstává
bashovým skriptem pod kontrolou člověka, takže je to jeho volba.

## Jednotky

| jednotka | zodpovědnost |
|---|---|
| `Cli\Arguments` | `$argv` → jméno workflow, pojmenované hodnoty, příznaky |
| `Cli\Application` | složí repozitáře, rozhodne mezi `--list` / `--help` / během, přeloží výjimky na návratové kódy |
| `bin/donut` | `exit((new Application)->run($argv));` |

`Arguments` je čistá funkce nad polem řetězců, testovatelná bez čehokoliv
dalšího. `Application` dostává v konstruktoru **adresář a streamy** (stdout,
stderr), aby šla testovat bez skutečného terminálu — stejný důvod, proč má
`Reporter` rozhraní.

### Argumenty

Jen tvar `--klic=hodnota`. Tvar se dvěma slovy (`--klic hodnota`) se
nezavádí: specifikace ho neuvádí a jednoznačnost je tu cennější než
pohodlí.

**Neznámý argument je chyba**, ne tiché ignorování. `--shortIdd=abc` skončí
s kódem 2 a hláškou. Je to tatáž třída překlepu, kterou celý nástroj
existuje odhalovat — nemá smysl ji hlídat uvnitř workflow a pustit ji na
vstupu.

**Zopakovaný argument je taky chyba.** `--tag=a --tag=b` neznamená ani „a",
ani „b" — znamená, že se autor spletl, a hádat za něj je horší než se
zastavit.

### Tvary volání

```
donut --list                          seznam workflow, konec 0
donut <workflow> --help               nápověda k workflow, konec 0
donut --help                          stručné použití, konec 0
donut <workflow> [--klic=hodnota …]   běh
donut                                 stručné použití, konec 2
```

`--list` vypisuje jen workflow, ne kameny — kameny jsou implementační
detail workflow a člověk je nespouští.

**Na pořadí nezáleží.** `donut --help card-dev` udělá totéž co
`donut card-dev --help` — rozklad argumentů pořadí nezachovává a rozhoduje
se jen podle toho, jestli přišlo jméno workflow a jestli je zvednutý
příznak. Ukázky výše píšou `--help` za jménem, protože tak to má
specifikace, ale vynucovat to by znamenalo přidat pravidlo, které nikomu
nepomůže.

### Standardní vstup

Když stdin není terminál, přečte se celý a předá jako klíč `STDIN`. Když je
terminál, `STDIN` je prázdný řetězec. V cronu bývá stdin `/dev/null`, tedy
prázdný — což je správně.

## Návratové kódy

| kód | význam |
|---|---|
| 0 | proběhlo celé |
| 1 | krok selhal za běhu — nepovolený exit code, timeout, nespuštěný proces, chybějící klíč v mapě |
| 2 | nespustilo se vůbec — neúspěšná validace, neexistující workflow, neznámý nebo chybějící argument |

Rozdíl mezi 1 a 2 existuje kvůli automatizaci: `jptq` z něj pozná, že
opakovat vadné workflow nemá smysl, zatímco krok, který selhal na
nedostupném Trellu, opakovat lze.

## Výstup

Průběh běhu jde přes `ConsoleReporter` na **stderr**, chyby taky. Stdout
zůstává vyhrazený pro `--list`, `--help` a pro výstup kroků, které si
`result` nemapují (viz níže).

```
donut --list
  card-dev     Zpracuje kartu agentem, výsledek pushne jako PR…
  card-spec    Zpracuje kartu agentem, výsledek zapíše jako komentář…
  repo-check   Zjistí, jestli k projektu existuje GitHub repozitář…
  sync         Projde sledované seznamy na Trello boardech…

donut card-dev --help
  card-dev — Zpracuje kartu agentem…

  Vstupy:
    --shortId=…       povinný   shortLink karty v Trellu
    --expectStatus=…  povinný   Seznam, ve kterém karta musí být
    --curlrc=…        volitelný Soubor s přihlašovací hlavičkou
```

## Dvě změny mimo CLI

Obě plynou z rozhodnutí o CLI a bez nich to nedrží pohromadě.

### Stdout kroku bez `out` teče na terminál

Dnes se standardní výstup zachytává vždy a zahodí se, když si ho krok
nenamapuje. Důsledek: `repo-check` nevypíše nic, ačkoliv jeho jediným
smyslem je něco říct. Totéž potká každý ladicí `echo` přidaný do workflow.

Nově platí u stdoutu **totéž pravidlo jako u stderr**: zachytit, když si ho
krok vyžádá do `out`, jinak nechat téct na terminál. Žádný nový pojem, jen
symetrie.

Mění to `NetteProcessRunner` (přibude `$captureStdout` vedle
`$captureStderr`), `Runner` a sekci 2 specifikace.

### Runner musí rozlišit „nespustilo se" od „selhalo za běhu"

Dnes hází `RunFailedException` na obojí, takže CLI nemá podle čeho vrátit
1 nebo 2. Přibude podtřída:

```php
Donut\Runner\CannotStartException extends RunFailedException
```

Hází se u neúspěšné validace a u chybějícího povinného vstupu workflow —
u dvou případů, kdy neproběhl ani jeden krok. Podtřída, ne samostatný typ,
aby stávající `catch` bloky dál fungovaly.

## Přejmenování klíčů na camelCase

Velká písmena byla doporučená proto, aby `%KLIC%` v textu nesplynulo
s okolím. S dvouznakovými delimitery `{%…%}` ten důvod padá a klíče se čtou
líp jako `{%cardJson%}`. Na příkazové řádce je rozdíl ještě větší:
`--shortId=abc` proti `--SHORT_ID=abc`.

Dělá se to teď, protože CLI ještě neexistuje a nikdo si na starý tvar
nezvykl.

### Tři kategorie, každá rozeznatelná na první pohled

| co to je | tvar | příklad |
|---|---|---|
| slovník formátu (klíče JSONu) | snake_case | `allow_failure`, `exit_code`, `stdin`, `type` |
| klíče mapy, které pojmenoval člověk | camelCase | `cardJson`, `shortId`, `prUrl` |
| klíče mapy dodané enginem | VELKÁ | `STDIN`, `CWD` |

Velká písmena tím začnou něco znamenat — „tohle jsi nepojmenoval ty" —
místo aby byla výchozí pro všechno.

### Kanál `stdin`

Plyne z toho, že se **jméno kanálu v `in` přejmenuje z `STDIN` na `stdin`**.
Je to jméno kanálu, ne klíč mapy, takže patří do slovníku formátu — a shodne
se s tím, jak ho kámen sám deklaruje:

```json
"stdin": { "required": true }        ← kámen deklaruje, že čte stdin
"in": { "stdin": "{%cardJson%}" }    ← krok ho plní hodnotou z mapy
"in": { "stdin": "{%STDIN%}" }       ← krok ho plní vstupem CLI volání
```

Poznámka o „optické kolizi" v sekci 1 specifikace tím zmizí — ta kolize
přestane existovat.

### Rozsah

47 jmen a 202 výskytů šablon v přepisu, plus deklarace v `inputs`, `in`,
`out`, `set.key` a `foreach.as`. Doporučení v sekci 3 specifikace. Tři místa
v kódu, která znají `STDIN` jako jméno kanálu — `Validator` dvakrát,
`Runner` jednou.

Pravidlo na tvar jména klíče (`[A-Za-z0-9_]+`, musí obsahovat písmeno) se
**nemění** — camelCase mu vyhovuje. Mění se doporučení, ne kontrola.

Ověřitelnost: přijímací test musí po přejmenování dál hlásit **0 chyb
a 0 varování** na referenční zátěži.

## Testy

**`Arguments` bez čehokoliv dalšího:** rozklad `$argv`, chybějící jméno
workflow, neznámý tvar argumentu, opakovaný argument, `--help` a `--list`
jako příznaky.

**`Application` s podstrčenými streamy a dočasným adresářem:** výpis
`--list`, nápověda k workflow, každý ze tří návratových kódů, hláška při
neexistujícím workflow, hláška při neznámém argumentu.

**Přijímací:** `bin/donut` se spustí jako skutečný proces nad dočasným
adresářem s kameny a workflow, a ověří se jeho stdout, stderr i návratový
kód. Bez toho by zůstalo neověřené právě to, co dělá z knihovny nástroj.

## Co zbývá dodělat

Vyřízeno. Nálezy z téhle sekce dodělal `2026-08-04-uklid-design.md` a jeho
plán; podrobnosti a zdůvodnění jsou tam.

Jedna položka zůstává vědomě neudělaná: **roury se v přijímacím testu čtou
sekvenčně**, bez `stream_select`. Při velikosti výstupu těch fixtur je to
bezpečné a připnutý deskriptor 0 vyřešil to, co skutečně hrozilo.

## Vědomě odložené

- **`--dry-run`.** Vypsat poskládané příkazové řádky bez spuštění by bylo
  užitečné, protože vypadávání skupin je záludné. `CommandLine` je proto
  samostatná jednotka a doplnit to půjde bez zásahu do `Runner`u.
- **Vlastní umístění kamenů.** Kdyby pracovní adresář přestal stačit,
  přibude proměnná prostředí nebo přepínač. Teď ne.
- **Barevný výstup, `--quiet`, `--verbose`.** Průběh na stderr a výsledek na
  stdout stačí; kdo chce ticho, přesměruje stderr.
