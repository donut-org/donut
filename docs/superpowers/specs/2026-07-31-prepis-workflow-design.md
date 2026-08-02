# Přepis bashových workflow do formátu donut — návrh

Datum: 2026-07-31

## Cíl

Ověřit navržený formát (`docs/format-specifikace.md`) tím, že se do něj
přepíšou reálně používaná bashová workflow z `docs/workflows/jpw/`. Ne
naprogramovat engine — zjistit, jestli formát unese skutečnou práci, a najít
v něm díry dřív, než se na něm začne stavět runner a GUI.

Výstup přepisu je v `docs/workflows/donut/`.

## Východisko

`jpw-*` skripty nejsou posloupnost příkazů předávajících si hodnoty. Je to
**jeden JSON payload protékající devatenácti transformátory**: každý `olw-*`
nástroj přečte payload ze stdin, přidá nebo změní klíč a vypíše celý payload
na stdout. Kroky si nepředávají hodnoty, předávají si stav.

Formát předpokládá opak — plochou mapu textů, kde si krok řekne, co číst
a kam zapsat. Přímý přepis 1:1 tedy neexistuje a bylo nutné rozhodnout,
kudy vede hranice.

## Rozhodnutí

### Přepsat i olw vrstvu

`jpw-*` se stávají workflow JSON soubory, `olw-*` slouží jako předloha pro
kameny. Mapa enginu drží skutečné hodnoty (`TITLE`, `REPO`, `WORK_DIR`,
`PR_URL`), ne obálku.

### Hranice kamene: podle povahy kroku

Kritérium — má krok vlastní řídicí logiku, nebo jen transformuje data?

- **Transformace dat** se rozpouští do obecných kamenů nad `curl` a `jq`
  přímo ve workflow. Týká se to všech čtení Trella, `comment-add`,
  `card-move`, `list-cards`, `olw-json`, `olw-text webalize`
  a `olw-task project-detect`.
- **Imperativní logika** zůstává příkazem: git/gh orchestrace, hledání
  repozitáře přes organizace, spuštění agenta, a dvě velké jq transformace
  (`trello-to-task`, `format-prompt`), které dávají smysl jako pojmenovaná
  operace se stdin→stdout.

Zmizí bez náhrady: `olw-lib.sh` a celý protokol s error obálkou (engine
zastavuje na nenulovém exit codu sám), `olw-json rename`/`pick` (plochá mapa
přejmenování nepotřebuje) a `olw-task status-assert` (nahrazuje `if` + kámen
nad `/usr/bin/false`).

### Data tečou stdin/stdout, ne souborem

Zadání původně říkalo „velká data nikdy v mapě, souborem". Obráceno: co se
vejde do paměti, ať zbytečně nechodí na disk. Reálné velikosti (task JSON
s komentáři jednotky až stovky kB) to unesou.

Důsledek je větší, než se čekalo: v celém přepisu nevznikne ani jeden
engine-rezervovaný soubor, takže z v1 vypadl celý podsystém kolem nich.

### Credentials mimo engine

Přihlašovací hlavička pro Trello žije v `curlrc` souboru, který si `curl`
načte sám (`--config`). Engine o secrets neví nic — žádné maskování, žádný
typ „secret", žádný únik do `ps` ani do debug logu. Stejný vzor jako `gh`
a `git`, které si autentizaci řeší samy.

### Prostředí: jen `CWD`

Engine nasype do mapy `STDIN` a `CWD`, nic dalšího. Cesty (`$HOME`, adresář
s prompty, `curlrc`, queue soubor) jsou běžné vstupy workflow a přicházejí
z příkazové řádky. `sync` je dostává jako své vstupy a protlačuje je do
příkazu, který zařazuje do fronty.

### Pět workflow → dva soubory

Dělicí čára jde po struktuře, ne po parametru: kdo zakládá PR, je `card-dev`
(developer, developer-haiku, assistent, assistent-haiku); kdo jen komentuje,
je `card-spec` (techlead). Rozdíly uvnitř skupiny jsou čistě `inputs`.
Žádný příznakový vstup řídící strukturu.

### `jpw-queue-consume` zůstává v bashi

Dohled nad dlouho běžícím procesem není workflow.

## Rozhraní nových `olw-*` příkazů

Bez JSON obálky. Argumenty vstup, stdout výstup.

| příkaz | argumenty | stdin | stdout | poznámka |
|---|---|---|---|---|
| `olw-trello-to-task` | `--my-id` (volitelný) | karta z API | task JSON | |
| `olw-task format-prompt` | — | task JSON | text promptu | |
| `olw-task repo-find` | `--project` | — | `owner/name` nebo nic | nenalezeno = prázdný výstup, ne chyba |
| `olw-workspace init` | `--root`, `--project`, `--repo`, `--branch` | — | cesta k workDir | bez `--repo` jen vytvoří adresář; webalizaci jména řeší uvnitř |
| `olw-workspace finish` | `--work-dir`, `--repo`, `--message` | — | — | bez `--repo` nedělá nic |
| `olw-workspace pr` | `--work-dir`, `--repo`, `--title` | tělo PR | URL **jen když PR sám vytvořil** | pro existující PR nevypíše nic |
| `olw-agent` | `--model`, `--system-prompt-file`, `--work-dir` | prompt | odpověď agenta | |

Volitelné argumenty musí snést, že nedorazí — skupina argumentů vypadne,
když je hodnota prázdná. Na tom stojí zpracování karet bez repozitáře.

## Co přepis přinesl

**Pět HTTP requestů se scvrklo na jeden.** `trello-to-task` potřebuje pět
JSON vstupů, ale kámen má jeden stdin. Řešením nebylo obcházet formát, ale
jedno API volání — Trello umí vrátit kartu včetně komentářů, příloh
a checklistů naráz. Omezení formátu odhalilo zbytečnost v bashi.

**Pravidlo o jednom průchodu šablon si vydělalo.** Mapou protéká cizí text —
komentáře z Trella, výstup agenta. To, že se výsledek dosazení dál
nezpracovává, je přesně to, co brání komentáři obsahujícímu `%NECO%` cokoliv
rozbít. V bashi to hlídá `jq --arg`, tady formát.

**Tvar jména klíče musel dostat pravidlo.** Sekce 3 slibovala, že neznámý
vzor projde beze změny, sekce 5 dělala z nezapisovaného klíče chybu — na
`%20%` v URL si to protiřečilo. Vyřešeno tím, že jméno klíče musí obsahovat
aspoň jedno písmeno, takže `%20%` šablona není. Doloženo to ale není:
v přepisu se percent-encoding nevyskytuje, všech 39 šablon je tvaru
`VELKÁ_PÍSMENA`. Pravidlo je navržené proti očekávanému použití.

**Zrušil se rozdíl mezi nevyplněno a `""`.** Podrobně v sekci 6 specifikace.
Krátce: krok nikdy nemůže vyrobit „nevyplněnou" hodnotu, takže by skupiny
argumentů fungovaly jen pro vstupy workflow, nikdy pro výstupy kroků.

## Ověření

Prototyp validátoru podle sekce 5 specifikace hlásí na přepisu
**0 chyb a 0 varování**. Na nastražených vadách chytá všech pět tříd chyb
(neexistující kámen, nedeklarovaný vstup, chybějící povinný stdin, překlep
v názvu klíče, neznámý operátor) i obě třídy varování.

Prototyp je jednorázový, není součástí repozitáře — sloužil k ověření návrhu,
ne jako implementace.

## Rozsah

Kroky včetně vnořených v `if` a `foreach`.

| soubor | kroků | nahrazuje |
|---|---|---|
| `card-dev.json` | 29 | `jpw-indev-developer`, `-developer-haiku`, `-assistent`, `-assistent-haiku` |
| `card-spec.json` | 25 | `jpw-indev-techlead` |
| `sync.json` | 37 | `jpw-indev-sync` |
| 14 kamenů | | 8 skriptů s ~20 subcommandy |

V `sync` je 37 kroků proto, že pět rolí (ToSpec, ReadyToDev,
ReadyToDev-Haiku, Assistent, Assistent-Haiku) je rozepsaných po čtyřech
skoro shodných krocích — `foreach` neumí rozdělit řádek na sloupce.

## Co z v1 vypadlo

**Souborové výstupy a úklid temp souborů.** Přepis žádný engine-rezervovaný
soubor nevytvoří, takže je celé odložené — rezervace jmen, evidence vlastních
souborů, statická analýza posledního použití klíče, mazání ve `finally`
i výjimka pro debug režim. Klíč `output` tím zůstal s jedinou legální
hodnotou a z formátu zmizel; `result` je vždy standardní výstup procesu.

## Co zůstává otevřené

- `foreach` neumí rozdělit řádek na sloupce, takže tabulka board × seznam ×
  role v `sync` je rozepsaná do pěti skoro shodných čtyřkrokových bloků.
- `%STDIN%` jako vstup prvního kroku zůstal neověřený — žádné z workflow
  nečte CLI stdin.
