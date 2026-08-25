# Zadání

CLI nástroj pro workflow. Krok = spuštění externího příkazu.
Formát souborů: viz `format-specifikace.md`.

## Stack

- PHP, Nette 3 (utils 4.1.4+)
- `Nette\Utils\Process::runExecutable()` — **nikdy `runCommand()`**
- Bez databáze
- CLI = vlastní wrapper nad `$argv`, žádné contributte
- GUI později, jako Nette app nad stejnými service třídami

## Model

- **Kámen** = 1 příkaz + deklarované vstupy. JSON v `blocks/`, odkaz jménem.
- **Workflow** = JSON v `workflows/`. Lineární seznam kroků, `if` a `foreach`
  mají vnořené `steps`.
- **Krok** = `run`, `if`, `set` nebo `foreach`. Nic víc.
- **Mapa enginu** = plochý key-value, **jen stringy**. Krok si řekne, co číst a kam zapsat.
- Kámen o mapě neví. Workflow o vnitřku kamene neví.

## Klíčová rozhodnutí

| Věc | Rozhodnutí |
|---|---|
| Argumenty | Pole, ne string. Žádný shell. |
| Skupiny argů | `[["-H","{%HEADER%}"]]` — skupina, v níž se proměnná vyhodnotí na prázdno, vypadne celá |
| Nevyplněno vs `""` | Totéž. Krok nikdy nevyrobí „nevyplněno“ — viz sekce 6 specifikace |
| Šablony | `{%KLIC%}`, jeden průchod, co tvaru neodpovídá projde beze změny, escape není potřeba |
| Čtení neexistujícího klíče | Tvrdá chyba, konec běhu |
| `if` větev | Nemá vlastní scope. Zápis ve větvi je vidět i za `if`. |
| Chyba kroku | Default stop. `allow_failure: [0,1]` pro `grep`/`test`. |
| Výstup kroku | `result` (stdout), `stderr`, `exit_code` |
| Soubory | Engine žádné nevytváří. Cesty jsou vstupy zvenčí. |
| Velká data | Stdin/stdout přes mapu. |
| Vstup 1. kroku | STDIN CLI volání, v mapě jako `STDIN` |
| Argumenty CLI | Pojmenované (`--ENV=prod`), podle `inputs` workflow |
| Kde jsou definice | Profil `$DONUT_HOME/$DONUT_PROFILE/{blocks,workflows}`, výchozí `~/.config/donut/default`. Pracovní adresář nerozhoduje; jeho jediná role je klíč `CWD` a adresář, ve kterém běží kroky. |

## Pořadí prací

0. ~~Přepsat existující bashová workflow → ověřit formát~~ — hotovo,
   viz `workflows/donut/` a sekce 6 specifikace
1. ~~Parser + **validátor**~~ — hotovo, viz sekce 5 specifikace
2. ~~Runner~~ — hotovo, viz `superpowers/specs/2026-08-03-runner-design.md`
3. ~~CLI wrapper~~ — hotovo, viz `superpowers/specs/2026-08-03-cli-design.md`
4. ~~Přepsat `olw-*` skripty podle rozhraní v návrhu~~ — hotovo,
   viz `superpowers/specs/2026-08-05-olw-prepis-design.md`. Obálka zmizela
   z celé vrstvy, `olw-lib.sh` smazaná. **Zbývá ověření na ostro:** bashová
   sada testuje jednotlivé příkazy, ne to, že si s donutem sedí argumenty.
   To prověří teprve `donut card-dev` na skutečné kartě.
5. ~~GUI~~ — hotovo, autorské prostředí, stavělo se po vrstvách,
   viz `superpowers/specs/2026-08-05-gui-design.md` a `gui/`
   - ~~vrstva 1: validace u kroku a přehled kamenů~~ — hotovo, jen pro čtení
   - ~~vrstva 2: tok klíčů~~ — hotovo,
     viz `superpowers/specs/2026-08-06-gui-vrstva2-design.md`
   - ~~vrstva 3: builder~~ — hotovo, rozpadala se na tři projekty:
     ~~serializér~~ (`superpowers/specs/2026-08-06-serializer-design.md`) — hotovo,
     ~~editace kamene~~ (`superpowers/specs/2026-08-13-editace-kamene-design.md`) — hotovo,
     editace workflow — rozpadala se na dva projekty: ~~kroky~~
     (`superpowers/specs/2026-08-14-editace-workflow-kroky-design.md`) — hotovo,
     a ~~obálka~~ (`superpowers/specs/2026-08-15-obalka-workflow-design.md`) — hotovo
6. ~~Profily a XDG cesty~~ — hotovo, viz
   `superpowers/specs/2026-08-25-profily-a-xdg-design.md`

Validátor dělej hned, ne potom. Je to hlavní přidaná hodnota proti Bashi
a GUI z něj bude žít.

## Úklid temp souborů

Odloženo. Přepis workflow ukázal, že po přechodu na stdin/stdout engine
žádný soubor nevytváří, takže není co uklízet. Až budou souborové výstupy
potřeba, vrátí se s nimi i evidence, analýza posledního použití klíče
a mazání ve `finally`.

## Vědomě odloženo

Neimplementovat, ale nezavřít si dveře:

- rozpad řádku ve `foreach` na víc klíčů podle oddělovače
- kámen se souborovým výstupem a úklid temp souborů
- `on_error: continue` / skok na krok
- historie běhů (až s ní přijde DB)
- paralelní větve
- volání workflow jako kroku jiného workflow
- odkazy na výstupy starších kroků než předchozího

## Co nedělat

- `sh -c`, skládání příkazu jako řetězce
- `eval` v podmínkách — jen `{left, op, right}`
- struktury v mapě (pole, objekty, čísla)
- extrakční jazyk v enginu — od toho je kámen s `jq`
- rekurzivní dosazování šablon
