# Přepis `olw-*` vrstvy — návrh

Datum: 2026-08-05

## Cíl

Zrušit v `olw-*` skriptech protokol s JSON obálkou a nahradit ho konvencí
„argumenty vstup, stdout výstup". Je to krok 4 z `docs/zadani.md` a poslední
věc, která chybí, aby donut mohl běžet na ostro.

Referenční rozhraní sedmi příkazů, které volá donut, je v
`docs/superpowers/specs/2026-07-31-prepis-workflow-design.md`, sekce
„Rozhraní nových `olw-*` příkazů".

## Kde se pracuje

V repozitáři `jptoolkit/olw`, na `master`. Tady je jen nagitignorovaný
checkout v `docs/workflows/olw/`.

Není to donut. Commity jdou jinam a `make test` v olw repu je bashová sada,
ne nette/tester.

## Východisko

`olw-*` skripty si dnes předávají **jeden JSON payload**: každý ho přečte ze
stdin, přidá nebo změní klíč a vypíše celý zpátky. `olw-lib.sh` k tomu drží
protokol — validaci vstupu, error obálku a zkrat, kterým cizí chyba proteče
rourou beze změny.

Donut ten protokol nepotřebuje ani nechce. Mapa enginu drží skutečné hodnoty
a engine se zastaví na nenulovém exit kódu sám.

## Rozsah

Přepisuje se **všech šest skriptů s obálkou**: `olw-agent`, `olw-task`,
`olw-trello-to-task`, `olw-workspace`, `olw-json` a `olw-trello`.

`olw-text` se nedotýkáme — obálku nikdy neměl a jeho rozhraní už dnes
odpovídá cílové konvenci.

Nic se nemaže **kromě** `olw-lib.sh` (protokol, který zaniká)
a `olw-task status-assert` (bez obálky by z něj zbylo porovnání dvou řetězců;
donut ho nahradil `if` + kámen nad `/usr/bin/false`).

`olw-task project-detect` **zůstává**. Donut ho sice nevolá — dělá totéž jq
krokem přímo ve workflow — ale jako samostatná transformace dává smysl dál.

### Co z toho donut doopravdy volá

Jen část. Zbytek se přepisuje kvůli konzistenci vrstvy a kvůli použitím mimo
donut:

| volá donut | nevolá donut |
|---|---|
| `olw-agent` | `olw-json` (celý) |
| `olw-task format-prompt`, `repo-find` | `olw-trello` (celý) |
| `olw-trello-to-task` | `olw-task project-detect` |
| `olw-workspace init`, `finish`, `pr` | |

Z toho plyne pravidlo o tvaru argumentů níže.

## Tvar argumentů

**Argumenty donut-facing příkazů nejsou volba tohohle návrhu.** Předepisují je
kameny v `docs/workflows/donut/blocks/` — každý drží přesné `args`, se kterými
se příkaz spustí. Změnit tvar argumentu tady znamená změnit kámen tam, tedy
zásah do druhého repozitáře.

Tvar `--klic=hodnota` v nich musí být proto, že donut zahodí celou skupinu
argumentů, jejíž proměnná se vyhodnotí na prázdno — na tom stojí zpracování
karet bez repozitáře.

**Příkazy, které donut nevolá, si nechají poziční argumenty tak, jak je mají
dnes.** Žádný takový tlak na ně nepůsobí a poziční tvar se u nich čte líp:
`pick a b c` proti `--key=a --key=b --key=c`.

V `olw-json` se tedy nemění nic než obálka. V `olw-trello` k tomu přibývá
volitelné `--text` a tlustá karta u `card`; poziční argumenty zůstávají.

## Rozhraní

### Příkazy, které volá donut

| příkaz | argumenty | stdin | stdout |
|---|---|---|---|
| `olw-agent` | `--model=`, `--system-prompt-file=`, `--work-dir=` | prompt | odpověď agenta |
| `olw-task format-prompt` | — | task JSON | text promptu |
| `olw-task repo-find` | `--project=` | — | `owner/name` nebo nic |
| `olw-trello-to-task` | `--my-id=` | tlustá karta | task JSON |
| `olw-workspace init` | `--root=`, `--project=`, `--repo=`, `--branch=` | — | cesta k workDir |
| `olw-workspace finish` | `--work-dir=`, `--repo=`, `--message=` | — | — |
| `olw-workspace pr` | `--work-dir=`, `--repo=`, `--title=` | tělo PR | URL, jen když PR sám založil |

Volitelné argumenty musí snést, že nedorazí.

### Ostatní

| příkaz | argumenty | stdin | stdout |
|---|---|---|---|
| `olw-task project-detect` | — | task JSON | task JSON s rozděleným `project`/`title` |
| `olw-json pick` | `<key>...` | JSON | JSON |
| `olw-json rename` | `<old> <new>` | JSON | JSON |
| `olw-trello me` | `[--text]` | — | JSON, s `--text` holé ID |
| `olw-trello card` | `<shortId>` | — | JSON tlusté karty |
| `olw-trello card-move` | `<shortId> <listName>` | — | — |
| `olw-trello comments` | `<shortId>` | — | JSON pole |
| `olw-trello attachments` | `<shortId>` | — | JSON pole |
| `olw-trello checklists` | `<shortId>` | — | JSON pole |
| `olw-trello comment-add` | `<shortId> [text]` | text, když není argument | — |
| `olw-trello boards` | `[--text]` | — | JSON, s `--text` jména po řádcích |
| `olw-trello list-cards` | `<board> <list> [--text]` | — | JSON, s `--text` shortLinky po řádcích |

`olw-trello` je wrapper nad Trello API, takže **výchozí výstup je JSON z API**.
`--text` je východisko pro praktický skalární tvar. `boards` a `list-cards`
dnes text vypisují rovnou — pod `--text` se zachovají přesně, jen přestanou
být výchozí.

Přihlašovací údaje `olw-trello` zůstávají v `~/.config/olw/trello.json`.
Donut si Trello řeší přes `curlrc` a `olw-trello` nevolá; ty dva světy se
nepotkají.

## Chybová konvence

Hláška na stderr, nenulový exit, na stdout nic.

Zaniká error obálka i zkrat, kterým cizí chyba protékala rourou beze změny.
Donut se zastaví na nenulovém exit kódu sám; v rouře je to věc volajícího
(`set -o pipefail`).

## Nesamozřejmé posuny v chování

**`olw-agent --model=` bere model přímo** (`sonnet`, `haiku`, `opus`), ne
jméno agenta (`claude-sonnet`). Tak to předává kámen a tak to deklaruje
`card-dev`. Hodnota `dummy` zůstává jako testovací hák — jen se z „agenta"
stane „model".

**`olw-agent` musí snést chybějící `--system-prompt-file=` a `--work-dir=`.**
Nepředaný systémový prompt znamená spustit agenta bez něj. Předaný
a neexistující je chyba. Nepředaný `--work-dir=` znamená běžet v aktuálním
adresáři.

**`olw-task repo-find` ztrácí stdin i zkratku „repo už je vyplněné".** To řeší
workflow. Nula nálezů je prázdný stdout a exit 0, ne chyba — na tom stojí
zpracování karet bez repozitáře. Víc nálezů je chyba.

**`olw-trello-to-task` čte jednu tlustou kartu**: komentáře z `.actions`,
dál `.attachments`, `.checklists` a `.list.name`. Vypíše **holý task objekt**
bez obalu `olw_task`. Pole zůstávají stejná jako dnes — `id`, `title`,
`status`, `project`, `repo`, `branch`, `description`, `comments`,
`attachments`, `checklists`.

**`olw-trello card` stahuje tlustou kartu vždycky**
(`?list=true&actions=commentCard&attachments=true&checklists=all`), aby
`olw-trello card <id> | olw-trello-to-task` dalo úplný task. Tím se `comments`,
`attachments` a `checklists` stanou nadbytečné — zůstávají, protože wrapperu
nad API sluší mít je, ne protože je někdo potřebuje.

**`olw-workspace init` vypíše cestu k workDir** a zahodí závěrečné `cd`, které
dnes nic nedělá. Webalizaci jména projektu řeší uvnitř voláním `olw-text`.
Bez `--repo=` jen založí adresář.

**`olw-workspace finish` dostává commit message zvenčí** přes `--message=`
místo skládání z payloadu. Bez `--repo=` nedělá nic.

**`olw-workspace pr` vypíše URL jen když PR sám založil.** U existujícího PR
nevypíše nic. Na tom stojí `if prUrl not_empty` v `card-dev`: u existujícího
PR se do komentáře řádek s URL nepřidá.

**`olw-json` se mění jen o obálku.** `pick` i `rename` dělají přesně totéž
a berou tytéž argumenty.

## Sdílený kód

Žádný. `olw-lib.sh` se maže bez náhrady.

Po zrušení obálky toho společného nezbývá tolik, aby se vyplatila knihovna:
chyba jsou dva řádky a rozklad argumentů si každý skript napíše explicitně,
protože zároveň odmítá přepínače, které nezná:

```bash
while [ $# -gt 0 ]; do
	case "$1" in
		--model=*)        MODEL="${1#*=}" ;;
		--work-dir=*)     WORK_DIR="${1#*=}" ;;
		*) echo "Error: unknown argument: $1" >&2; exit 1 ;;
	esac
	shift
done
```

Obecný parser do asociativního pole by neznámý přepínač odmítnout nedokázal
bez další deklarace, takže by neušetřil skoro nic a přidal magii. Skripty se
navíc instalují symlinky jeden po druhém, takže jejich samostatnost je
i praktická.

`olw-workspace` dál volá `olw-text webalize` — to není knihovna, ale sourozenec.

## Testy

Šest sad se přepíše na nové rozhraní: `test-olw-agent.sh`,
`test-olw-json.sh`, `test-olw-task.sh`, `test-olw-trello.sh`,
`test-olw-trello-to-task.sh`, `test-olw-workspace.sh`.

Smaže se `test-olw-lib.sh` a `test-pipeline-errors.sh` — testují protokol,
který zaniká. `test-all.sh` se upraví podle toho.

`test-olw-text.sh` zůstává beze změny.

Stubování zůstává, jak sada umí dnes: podvržený `gh`, `claude` nebo `curl`
v dočasném adresáři na začátku `PATH`. Testovací rámec `test-helper.sh`
(`assert_eq`, `assert_exit_0`, `assert_exit_nonzero`, `summary`) se nemění.

Každý přepsaný příkaz dostane testy na to, co se mění: rozklad argumentů
včetně odmítnutí neznámého přepínače, chování při chybějícím volitelném
argumentu, tvar stdout, a exit kód při chybě.

Zvlášť je potřeba pokrýt tři místa, kde tiché selhání nikdo nepozná:

- **`olw-trello-to-task`** — čtyřicet řádků jq nad tlustou kartou. Komentáře
  seřazené podle data, `role` podle `--my-id=`, prázdné pole když sekce chybí.
- **`olw-task repo-find`** — nula nálezů musí dát exit 0 a prázdný stdout,
  ne chybu.
- **`olw-workspace pr`** — existující PR nesmí vypsat nic.

## Ověření na ostro

Přepis je hotový, až `donut card-dev` projde na skutečné kartě. Bashová sada
ověřuje jednotlivé příkazy, ne to, že si s donutem sedí argumenty.

## Co se vědomě nedělá

- **Sjednocení credentials.** `olw-trello` čte `~/.config/olw/trello.json`,
  donut používá `curlrc`. Donut `olw-trello` nevolá, takže se nepotkají;
  sjednocení by rozbilo použití mimo donut a nic by nevyřešilo.
- **Rozpad `olw-workspace` a `olw-task` na samostatné skripty.** Kameny volají
  podpříkazem (`olw-workspace init`), a ty jsou zafixované přijímacím testem
  donutu.
- **Přepis `jpw-*` skriptů.** Nahrazuje je donut. `jpw-queue-consume` zůstává
  v bashi, protože dohled nad dlouho běžícím procesem není workflow.
