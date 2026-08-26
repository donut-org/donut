# Anglické texty aplikace — návrh

Datum: 2026-08-26

## Cíl

Všechno, co je součástí kódu, přejde do angličtiny: texty, které
aplikace vypisuje (hlášky CLI, výjimky, UI v Latte šablonách),
komentáře v kódu a české identifikátory v testech.

Česky zůstává jen dokumentace a Honzův vlastní obsah — viz „Co se
vědomě nedělá".

## Proč

Kód a jeho výstup jsou jedna vrstva a mají mluvit jedním jazykem.
Dnešní stav je smíšený: `readme.md` je anglicky a slibuje anglický
nástroj, ale první hláška, kterou uživatel uvidí, je česky. Komentáře
u anglických identifikátorů popisují české hlášky, takže se u každého
řádku přepíná jazyk.

Není to nová preference. Commit messages **měly být anglicky vždy**;
250 českých commitů v historii a věta „komentáře česky" v Global
Constraints každého dosavadního plánu byly chyba, která se kopírovala
z plánu do plánu. Historie se nepřepisuje, ale od tohoto projektu dál
platí opak.

## Rozsah

| co | jazyk po projektu |
|---|---|
| hlášky, výjimky, nápověda CLI | anglicky |
| UI v Latte šablonách | anglicky |
| komentáře v `src/`, `gui/src/`, testech | anglicky |
| identifikátory v testech | anglicky |
| commit messages | anglicky, od tohoto projektu |
| `docs/*.md`, `docs/superpowers/` | **česky** |
| `docs/workflows/donut/` | **česky** |

Produkční identifikátory se nemění — `src/` ani `gui/src/` žádné české
nemají, ověřeno.

**Fixtura psaná přímo v testu se překládá** — jména workflow a kamenů
jako `'pozdrav'`, `'hlasite'`, `'prazdne'` jsou součást testu, ne
uživatelský obsah. Pozor: většina z nich diakritiku nemá, takže je
závěrečný grep nenajde; hledají se očima.

**Fixtura čtená z `docs/workflows/donut/` se nepřekládá** — viz výjimku
u osmi testů níž.

Váha projektu:

| | |
|---|---|
| české řetězce | 132 v `src/`, 83 v `gui/src/` (z toho 113 v `throw`/`fwrite`) |
| řádky s češtinou v Latte | 149 v 9 šablonách |
| komentáře s diakritikou | ~1 700 (228 `src/` + 417 `gui/src/` + 1 051 testy) |
| aserce tvrdící o českém textu | **194** (175 z toho v `gui/tests`) |
| české identifikátory v testech | 13 (`spust`, `prazdny`, `bezAdresare`, `hlaska`, `jinde`, …) |

## Glosář

Slovník je závazný. Bez něj se 113 hlášek rozejde a v desátém tasku už
se nesjednotí.

| česky | anglicky | proč zrovna takhle |
|---|---|---|
| kámen | `block` | už je to identifikátor 14 tříd, adresáře `blocks/` i klíče `"block"` ve formátu; „stavební kámen" je doslova *building block* |
| krok | `step` | |
| workflow | `workflow` | beze změny |
| klíč | `key` | |
| obálka | `envelope` | jméno a kroky kolem workflow |
| profil | `profile` | |
| adresář | `directory` | ne *folder* — CLI mluví o souborovém systému |
| vstup / výstup | `input` / `output` | `in`/`out` ve formátu už anglicky jsou |
| jméno | `name` | |
| řetězec | `string` | ve validátoru je to typ, ne text |
| pole | `array` | |
| objekt | `object` | |
| povinný | `required` | |
| neznámý | `unknown` | |
| výchozí | `default` | |
| neexistuje | `does not exist` | ne *is missing* — hláška říká, že se hledalo a nenašlo |

**`node` se pro kámen nepoužije.** V `gui/src/Presentation/Workflow/steps.latte:38`
je `<div class="node">` a uzel tam znamená *krok*. Dvě různé věci pod
jedním jménem, zrovna v pohledu, kde jsou vidět obě. Přejmenování domény
na `node` by navíc nebyl překlad, ale změna formátu souborů: adresář
`blocks/` na disku a klíč `"block"` uvnitř každého kroku. Kdyby se na to
přece jen mělo přejít, je to samostatný projekt s vlastní migrací, až
po překladu.

## Ustálené tvary hlášek

Osmdesát ze 113 hlášek má jeden ze čtyř tvarů. Drž je doslova:

```
Chyba: <věta>
  →  Error: <sentence>

{$location}: klíč 'x' je povinný a musí být neprázdný řetězec.
  →  {$location}: key 'x' is required and must be a non-empty string.

Adresář s kameny '<cesta>' neexistuje.
  →  Blocks directory '<path>' does not exist.

Hledal jsem v: <cesta>
  →  Searched in: <path>
```

Poslední dva jsou **volnější překlad schválně**: doslovné *Directory
with blocks* je kostrbaté. Sourozenec zní `Workflows directory '<path>'
does not exist.`

Tečka na konci hlášek zůstává, jak je dnes.

## Rozklad

Zdola nahoru. `Parser` a `Validator` neznají nikoho, `Cli` a prezentéry
znají všechno — kdyby glosář někde nestačil, projeví se to v tasku 1 na
46 hláškách, ne v tasku 10 nad rozestavěným GUI.

V **každém** tasku se překládají řetězce, komentáře i testy té vrstvy
najednou. Sada je zelená po každém tasku.

| # | vrstva | řetězce | koment. | testy, které jdou s ní |
|---|---|---|---|---|
| 1 | `src/Parser/` | 46 | 17 | `BlockParser.*`, `WorkflowParser.*` |
| 2 | `src/Validator/` | 36 | 41 | `BlockValidator`, `Validator.*`, `ConditionEvaluator` |
| 3 | `src/Runner/` + `src/Format/` | 22 | 74 | `Runner.*`, `ConsoleReporter`, `NetteProcessRunner`, `CommandLine`, `acceptance.*` |
| 4 | `src/Writer/` + `src/*.php` | 15 | 63 | `*Writer`, `BlockRepository`, `Profile`, `MissingDir`, `Template.*` |
| 5 | `src/Cli/` + `bin/donut` | 13 | 33 | `Cli.Application`, `Cli.Arguments`, `Cli.acceptance` |
| 6 | `gui/src/*.php` — stores, mappery | 23 | 226 | `BlockStore`, `WorkflowStore`, `*Mapper`, `KeyMap`, `StepPath`, … |
| 7 | `gui/src/Presentation/*.php` | 60 | 191 | 79 asercí o výjimkách a hodnotách |
| 8 | `@layout.latte` | 11 | — | `Layout.phpt` |
| 9 | Block šablony (`edit`, `default`, `detail`) | 60 | — | `BlockPresenter.*` — 5 souborů |
| 10 | Workflow šablony (`steps`, `edit`, `detail`, `step`, `default`) | 78 | — | `WorkflowPresenter.*` — 8 souborů |

Šablony se nedělí od testů prezentérů: **92 ze 175 českých asercí v GUI
tvrdí o vyrenderovaném HTML**, takže změna v Latte shodí aserce
roztroušené po třinácti souborech. Proto tasky 8–10 dělí GUI po sekcích,
ne na „PHP zvlášť, šablony zvlášť".

## Jak se hlídá, že se nic neztratí

Riziko projektu není překlad. Je to 194 příležitostí zaměnit

```php
Assert::contains("Blocks directory '{$dir}' does not exist.", $err);
```

za

```php
Assert::contains('does not exist', $err);
```

Oslabená aserce vypadá jako překlad a projde. Tři síta:

1. **Počet `Assert::` v souboru nesmí klesnout.** Mechanické, chytá
   smazání. Nechytá oslabení.
2. **Report tasku nese tabulku před → po pro každou dotčenou aserci.**
   Při nejvýš čtyřiceti na task je čitelná a reviewer je čte vedle sebe,
   místo aby je lovil v diffu. Tohle je hlavní síto.
3. **Mutace na konci tasku** přidá vadu a musí shodit konkrétní
   přeloženou aserci — ne dřívější. Odebrání správného chování shodí
   aserci dřív a nedokáže nic.

**Úplnost.** Po každém tasku vrací

```
grep -rP "[áčďéěíňóřšťúůýž]" <soubory tasku>
```

prázdno, s jedinou výjimkou níže. Grep neodhalí češtinu bez diakritiky
(„Neexistuje", „Chyba"), takže každý task navíc jednou přečte své
soubory očima.

### Výjimka: osm testů čte českou fixturu

Tyhle soubory čtou `docs/workflows/donut/` jako testovací data, a ta
zůstávají česky:

- `tests/Donut/Writer.roundTrip.phpt`
- `tests/Donut/acceptance.negative.phpt`
- `tests/Donut/acceptance.rewrite.phpt`
- `gui/tests/StepMapper.phpt`
- `gui/tests/StepTree.phpt`
- `gui/tests/KeyMap.ValidatorContract.phpt`
- `gui/tests/StepPath.parse.phpt`
- `gui/tests/InputMapper.phpt`

Diakritika v nich, která pochází z fixtury, je v pořádku. Komentáře a
identifikátory v nich se překládají jako všude jinde. Výjimka je
vyjmenovaná schválně: plošný grep by tu hlásil falešný poplach a
někdo by ho „opravil" tím, že přepíše Honzovu automatizaci.

`gui/tests/KeyMap.ValidatorContract.phpt:20` to říká i v komentáři:
„docs/workflows/donut/ je tu testovací data, ne kód".

## Co se vědomě nedělá

- **`docs/*.md` a `docs/superpowers/` se nepřekládají.** Specifikace,
  plány a zadání zůstávají česky. Běhové hlášky necitují doslova,
  ověřeno — překlad je nezastará.
- **`docs/workflows/donut/` se nepřekládá.** Je to Honzův obsah, ne text
  aplikace; `--help` ho vypisuje jen proto, že si ho tak napsal.
- **Produkční identifikátory se nepřejmenovávají.** Žádné české tam
  nejsou.
- **`kámen → node`** — viz glosář.
- **Historie commitů se nepřepisuje.** 250 českých zpráv zůstává; anglicky
  se píše od tohoto projektu dál.
- **Nezavádí se lokalizace.** Žádné `gettext`, žádný katalog, žádný
  přepínač jazyka. Aplikace mluví anglicky, tečka. Přidat překlad zpět
  jde kdykoli, ubrat vrstvu hůř.

## Testy

Projekt nepřidává testy — přepisuje aserce těch stávajících. Pravidlo
„žádná stávající aserce se nesmí oslabit ani smazat" je tu ta nejdůležitější
věta v celém návrhu; dostane 194 příležitostí se porušit.

`tests/Donut/Writer.roundTrip.phpt:97-98` testuje **neplatné UTF-8
schválně**. Ta bajtová fixtura se nesmí „uklidit" ani přeložit; překládá
se jen komentář nad ní.
