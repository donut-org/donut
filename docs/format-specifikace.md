# Formát JSON — stavební kameny a workflow

Verze návrhu 0.3. Ověřeno přepisem existujících bashových workflow;
změny proti 0.2 vycházejí z toho, na co přepis narazil — viz sekce 6.

---

## 1. Stavební kámen (`blocks/<jmeno>.json`)

Kámen je parametrizovaná funkce nad jedním příkazem. Neví nic o workflow,
které ho volá, ani o klíčích v mapě enginu.

```json
{
  "name": "curl-get",
  "description": "HTTP GET. Tělo odpovědi jde na stdout.",

  "command": "curl",

  "args": [
    ["-sS", "--fail"],
    ["--config", "{%curlrc%}"],
    ["{%url%}"]
  ],

  "inputs": {
    "url":    { "required": true,  "description": "Úplná adresa včetně query stringu" },
    "curlrc": { "required": false, "description": "Soubor s přihlašovací hlavičkou" }
  },

  "timeout": 60,
  "allow_failure": false
}
```

Kámen čtoucí ze standardního vstupu:

```json
{
  "name": "jq",
  "description": "Transformace JSON/textu na stdin filtrem jq.",

  "command": "jq",
  "args": [["{%flags%}"], ["{%filter%}"]],

  "inputs": {
    "flags":  { "required": false, "description": "Volby jq v jednom tokenu: -r, -Rs …" },
    "filter": { "required": true,  "description": "Program jq" }
  },
  "stdin": { "required": true }
}
```

### Klíče

| klíč | povinné | popis |
|---|---|---|
| `name` | ano | Musí odpovídat názvu souboru. Kroky se odkazují tímto jménem. |
| `description` | ne | Text pro `--help` a GUI. |
| `command` | ano | Název programu. Bez argumentů, bez shellu. |
| `args` | ano | Pole **skupin**. Každá skupina je pole řetězců. |
| `inputs` | ne | Deklarace proměnných dosazovaných do `args`. |
| `stdin` | ne | Přítomnost znamená, že kámen čte standardní vstup. |
| `timeout` | ne | Sekundy. Default 60 (viz `Nette\Utils\Process`). |
| `allow_failure` | ne | Které exit kódy jsou v pořádku. Viz níže. |

Výstupem kamene je vždy standardní výstup procesu. Kámen zapisující do
souboru je odložený, viz sekce 6.

### `allow_failure`

```json
"allow_failure": false      // jen 0; cokoliv jiného ukončí workflow (default)
"allow_failure": true       // libovolný exit code je v pořádku
"allow_failure": [0, 1]     // 0 a 1 jsou v pořádku, ostatní ukončí workflow
```

Seznam existuje proto, že unixové nástroje rozlišují víc než „povedlo se"
a „nepovedlo": `grep` vrací 1 pro „nenalezeno" a 2 pro chybu, `test` a `diff`
totéž, `jptq task` 1 pro „úloha už ve frontě". S booleanem se buď zastavíš
na běžném stavu, nebo přehlédneš skutečné selhání.

Exit code se dá bez ohledu na tohle nastavení uložit do mapy kanálem
`exit_code` a rozhodovat se podle něj v `if`.

### Skupiny argumentů

`args` je pole polí. Výsledná příkazová řádka vznikne zploštěním skupin.

**Pravidlo:** skupina, ve které se některá `{%VAR%}` vyhodnotí na prázdno,
se celá vynechá.

```
curlrc prázdný  → curl -sS --fail https://…
curlrc vyplněný → curl -sS --fail --config /home/…/trello.curlrc https://…
```

„Prázdno" pokrývá obojí — nevyplněný volitelný vstup i vstup vyplněný
prázdným řetězcem. Jsou to **jedna a táž věc**; verze 0.2 je rozlišovala,
viz sekce 6.

U vstupu s `required: true` je prázdná hodnota **tvrdá chyba**, ne vynechání
skupiny. Jinak by povinný argument tiše zmizel a `required` by nic neznamenalo.

Potřebuješ-li předat opravdu prázdný argument, napiš ho v `args` jako
konstantu (`["--prefix="]`). Bez proměnné není co vyhodnotit na prázdno,
takže skupina nikdy nevypadne.

Dosazení probíhá do jednotlivých prvků skupiny, nikdy do celé řádky.
Prvek může obsahovat víc proměnných i okolní text (`"--url={%url%}"`).

### `inputs`

```json
"inputs": {
  "vzor":  { "required": true },
  "limit": { "required": false, "default": "10" }
}
```

| klíč | default | popis |
|---|---|---|
| `required` | `true` | Volitelný vstup musí mít `required: false`. |
| `default` | — | Použije se, když krok hodnotu nepředá. |
| `description` | — | Text pro GUI. |

### `stdin`

```json
"stdin": { "required": true, "description": "Task JSON" }
```

Samostatný klíč, ne položka v `inputs` — kámen má nejvýše jeden standardní
vstup a struktura to vynucuje sama, bez pravidla ve validaci.

Krok ho plní stejně jako ostatní vstupy, pod jménem `stdin`:

```json
"in": { "filter": ".title", "stdin": "{%task%}" }
```

Vstup CLI volání se pak plní `"in": { "stdin": "{%STDIN%}" }` — vlevo jméno
kanálu, vpravo klíč, který do mapy dodal engine.

`{%STDIN%}` se **nikdy nedosazuje do `args`** — je to označení kanálu, ne
proměnná.

Bez `stdin` dostane proces prázdný standardní vstup.

**Stdin je hlavní cesta pro data.** Prompt pro agenta, task JSON, tělo
komentáře — to všechno teče stdin→stdout mezi kroky přes mapu. Engine
nevytváří ani neuklízí žádné soubory; cesty, které ve workflow vystupují
(`curlrc`, systémový prompt, queue soubor), jsou vstupy zvenčí.

---

## 2. Workflow (`workflows/<jmeno>.json`)

```json
{
  "name": "deploy",
  "description": "Zjistí verzi API na daném prostředí.",

  "inputs": {
    "env": { "required": true,  "description": "prod | staging" },
    "tag": { "required": false, "default": "latest" }
  },

  "steps": [
    {
      "type": "run",
      "block": "curl-get",
      "in":  { "url": "https://api.example.com/{%env%}/status" },
      "out": { "result": "status", "exit_code": "rc" }
    },
    {
      "type": "if",
      "condition": { "left": "{%rc%}", "op": "eq", "right": "0" },
      "then": [
        {
          "type": "run",
          "block": "jq",
          "in":  { "flags": "-r", "filter": ".version", "stdin": "{%status%}" },
          "out": { "result": "verze" }
        }
      ],
      "else": [
        { "type": "set", "key": "verze", "value": "neznámá" }
      ]
    }
  ]
}
```

Krok je `run`, `if`, `set` nebo `foreach`.

### Krok `run`

| klíč | povinné | popis |
|---|---|---|
| `type` | ano | `"run"` |
| `block` | ano | Jméno kamene z `blocks/`. |
| `in` | ne | Mapa `vstup kamene → šablona`. Klíč `stdin` plní standardní vstup. |
| `out` | ne | Mapa `kanál → klíč v mapě enginu`. |
| `name` | ne | Popisek pro log a GUI. Nemá vliv na běh. |
| `allow_failure` | ne | Přepíše nastavení kamene pro tento krok. |
| `timeout` | ne | Přepíše nastavení kamene pro tento krok. |

Hodnoty v `in` jsou šablony — text s `{%KLIC%}`. Klíč, který v mapě neexistuje,
je **tvrdá chyba a konec běhu**.

`out` přijímá kanály:

| kanál | obsah |
|---|---|
| `result` | standardní výstup procesu |
| `stderr` | chybový výstup |
| `exit_code` | návratový kód jako text (`"0"`) |

U `result` a `stderr` se **odřezává koncové odřádkování**, stejně jako to
dělá `$(...)` v shellu. Bez toho by `jq -r '.id'` vrátil `5f2abc\n` a ta
hodnota by se pak vlepila doprostřed URL. Vnitřní odřádkování zůstává
nedotčené — odřezává se jen konec.

Kanál, který krok v `out` neuvede, se zahodí — krok mapující jen `result`
zahazuje `stderr` i `exit_code`. Krok bez `out` mapu nemění.

Něco jiného je jméno, které kanál **vůbec není** (`stdout`, `retcode`).
Takový klíč by nikdo nikdy nezapsal, zatímco autor workflow počítá s tím,
že vznikne. To je překlep a validace ho odmítne, viz sekce 5.

### Krok `if`

| klíč | povinné | popis |
|---|---|---|
| `type` | ano | `"if"` |
| `condition` | ano | Viz níže. |
| `then` | ano | Pole kroků. Smí být prázdné. |
| `else` | ne | Pole kroků. |
| `name` | ne | Popisek pro log a GUI, stejně jako u `run`. |

Větve nezavádějí vlastní scope — zápis uvnitř větve je vidět i za `if`.
Vnořování je povolené do libovolné hloubky.

Zastavit běh ve větvi se dá kamenem nad `/usr/bin/false`; důvod nese `name`
kroku, který se objeví v logu.

### Krok `set`

| klíč | povinné | popis |
|---|---|---|
| `type` | ano | `"set"` |
| `key` | ano | Klíč v mapě enginu. |
| `value` | ano | Šablona. |
| `name` | ne | Popisek. |

Jediný způsob, jak složit hodnotu z jiných hodnot bez spuštění procesu.
Čtení vlastního klíče je v pořádku — dosazuje se jedním průchodem, takže
`{"key":"x","value":"{%x%} a něco"}` přečte starou hodnotu a zapíše novou.

### Krok `foreach`

| klíč | povinné | popis |
|---|---|---|
| `type` | ano | `"foreach"` |
| `over` | ano | Šablona. Hodnota se rozdělí na řádky. |
| `as` | ano | Klíč, do kterého se ukládá aktuální řádek. |
| `steps` | ano | Pole kroků. |
| `name` | ne | Popisek. |

Dělí se na `\n`, `\r` na konci řádku se odřízne, prázdné řádky se přeskočí.
Nula řádků znamená nula iterací.

Tělo nemá vlastní scope, stejně jako `if`. Klíč z `as` je běžný klíč mapy
a po skončení cyklu v něm zůstane poslední zpracovaný řádek.

Řádek se nedá rozdělit na sloupce. Tabulku (board × seznam × role) je tedy
nutné rozepsat; viz sekce 6.

Vnořování je povolené — `sync` iteruje přes boardy a uvnitř přes karty.

### Podmínka

```json
{ "left": "{%rc%}", "op": "eq", "right": "0" }
```

`left` i `right` jsou šablony. Operátory:

| op | význam |
|---|---|
| `eq`, `neq` | porovnání řetězců |
| `gt`, `gte`, `lt`, `lte` | číselné porovnání; nečíselná hodnota = chyba |
| `contains` | `left` obsahuje `right` |
| `empty`, `not_empty` | `right` se ignoruje a nemusí být uveden |

Všechny ostatní operátory `right` **vyžadují**. Podmínka bez něj by
porovnávala s ničím a validace ji odmítne.

---

## 3. Mapa enginu

- Klíče jsou case-sensitive. Doporučení:
  - **slovník formátu** (klíče JSONu) je snake_case: `allow_failure`, `exit_code`, `stdin`
  - **klíče, které pojmenuješ sám**, jsou camelCase: `cardJson`, `shortId`
  - **klíče dodané enginem** jsou velkými: `STDIN`, `CWD`

  Velká písmena tím značí „tohle jsi nepojmenoval ty".
- **Všechny hodnoty jsou text.** Žádné pole, objekty, čísla.
- Počáteční obsah: vstupy workflow podle jmen, `STDIN` (standardní vstup
  CLI volání; prázdný řetězec, když nic nepřišlo) a `CWD`.
- Nic dalšího z prostředí neprochází. Cesty (`$HOME`, adresáře s prompty,
  konfigurace) jsou běžné vstupy workflow a přicházejí z příkazové řádky.
- Zápis do existujícího klíče ho přepíše.

### Šablonování

- Tvar `{%KLIC%}`. Jméno klíče je `[A-Za-z0-9_]+`.
- **Co tvaru neodpovídá, projde beze změny.** Samotné procento nic neznamená,
  takže `date +%Y`, `printf '%d\n'`, `100% hotovo` i percent-encoding v URL
  (`?path=%2Ffoo`, `%2F%3A`) fungují bez jakéhokoliv escapování.
- **Žádný escape neexistuje a není potřeba.** `{%` ani `%}` nevznikne
  percent-encodingem — byly by to `%7B` a `%7D`. Jediný text, který takhle
  nejde napsat, je literální `{%NECO%}`; kdyby to někdy bylo potřeba, escape
  se doplní.
- Dosazuje se **jedním průchodem**; výsledek se dál nezpracovává, takže data
  obsahující `{%NECO%}` se nevyhodnocují. Tohle pravidlo je to, co dovoluje
  protahovat mapou cizí text — komentáře z Trella, výstup agenta —
  bez rizika, že se něco v datech vyhodnotí.

Delimitery jsou dvouznakové právě kvůli tomu, aby se nesrážely s procentem
v datech. Dřívější jednoznakový tvar `%KLIC%` se s URL a formátovacími
řetězci srážel a vyžadoval escapování i pravidlo o tvaru jména; obojí
tímhle odpadá.

---

## 4. CLI

```
nastroj <workflow> --ENV=prod --TAG=v1.2
nastroj <workflow> --help
nastroj --list
cat data.txt | nastroj <workflow> --ENV=prod
```

Argumenty jsou pojmenované podle `inputs` workflow. Chybějící povinný vstup
je chyba před spuštěním prvního kroku.

---

## 5. Statická validace (před během)

**Chyby (běh se nespustí)**
- kámen neexistuje
- povinný vstup kamene nemá hodnotu v `in` ani `default`
- povinný `stdin` kamene není v `in` naplněn
- `in` obsahuje jméno, které kámen nedeklaruje (ani `stdin`, když kámen `stdin` nemá)
- šablona čte klíč, který **žádný krok nikdy nezapisuje** (překlep)
- šablona čte klíč, který v žádné předchozí větvi nemohl vzniknout
- podmínka nebo `foreach.over` čte klíč, který nemohl vzniknout
- `{%STDIN%}` použito v `args`
- `args` kamene odkazuje proměnnou, kterou kámen nedeklaruje jako `inputs`
- `out` uvádí jméno, které není kanál (`result`, `stderr`, `exit_code`)
- neznámý operátor v podmínce
- binární operátor v podmínce nemá `right`
- objekt obsahuje klíč, který formát nezná — a to na **každé** úrovni, ne
  jen v kořeni souboru. `"esle"` místo `"else"` by jinak tiše zahodilo celou
  větev a překlep v `allow_failure` u kroku by tiše vrátil chování kamene.
  Formát je uzavřený, takže není důvod cizí klíče tolerovat.
- `allow_failure` není `true`, `false` ani pole celých čísel
- klíč v `out`, `set.key` nebo `foreach.as` není platné jméno
  (`[A-Za-z0-9_]+`) — jinak by vznikl klíč, na který se nedá odkázat

**Varování**
- šablona čte klíč zapsaný jen v jedné větvi `if` nebo uvnitř `foreach`
  (může, ale nemusí existovat)
- klíč se zapisuje a nikdy nečte
- vstup workflow se nikde nepoužívá

Rozdíl mezi první a druhou odrážkou u chyb je podstatný: klíč, který nikdo
nikdy nezapisuje, je překlep a musí spadnout. Klíč zapisovaný podmíněně je
legitimní a smí projít s varováním.

---

## 6. Co ověřil přepis bashových workflow

Přepsáno: `jpw-indev-techlead`, `-developer`, `-developer-haiku`,
`-assistent`, `-assistent-haiku` (→ `card-dev` 29 kroků, `card-spec`
25 kroků) a `jpw-indev-sync` (→ `sync` 37 kroků). Počítáno včetně kroků
vnořených v `if` a `foreach`. Nad 15 kameny.

K nim přibylo `repo-check` (5 kroků), které přepisem nevzniklo. Žádné ze
tří přepsaných workflow nemá větev `else` — bash se nikde nevětví na dvě
strany — a ta mezera nechala projít chybný předpoklad o klíči zapsaném
v obou větvích `if`. `repo-check` tu cestu pokrývá.
`jpw-queue-consume` zůstává v bashi — je to dohled nad procesem, ne workflow.

### Změny proti verzi 0.2

| změna | co si ji vynutilo |
|---|---|
| přibyl `foreach` | `sync` iteruje přes karty vrácené API |
| přibyl `set` | složení komentáře z výsledku agenta a odkazu na PR |
| šablony mají tvar `{%KLIC%}`, ne `%KLIC%` | jednoznakový delimiter se srážel s procentem v URL a formátovacích řetězcích; s dvouznakovým odpadá escape i pravidlo o tvaru jména |
| `allow_failure` bere i pole exit kódů | `jptq task` vrací 1 pro „už ve frontě" a jiné kódy pro selhání |
| **zrušen rozdíl mezi „nevyplněno" a `""`** | viz níže |
| **odložen `output` a souborové výstupy** | přechod na stdin/stdout je učinil nepotřebnými |
| do mapy přibylo `CWD`, nic jiného z prostředí | cesty patří na příkazovou řádku |

### Zrušení rozdílu „nevyplněno" vs `""`

Verze 0.2 je vedla jako dvě různé věci. Přepis ukázal, že to nejde udržet:
**krok nikdy nemůže vyrobit „nevyplněnou" hodnotu.** Stdout příkazu je vždy
řetězec, takže když `repo-find` nenajde repozitář, v mapě skončí `""` —
což by byla „vyplněná" hodnota a skupina `["--repo={%repo%}"]` by nevypadla.
Skupiny argumentů by tak fungovaly pro vstupy workflow, ale nikdy pro
výstupy kroků.

Zvažovaná varianta byla nechat kámen deklarovat, že prázdný výstup znamená
nepřítomnost hodnoty. Vyžadovala by ale, aby čtení chybějícího klíče
přestalo být tvrdou chybou u volitelných vstupů — tedy oslabení hlavní
pojistky proti bashi.

Sloučení obou případů to řeší bez ztráty: v celém přepisu není jediné místo,
kde by mělo smysl předat prázdný argument, a bash sám používá totéž pravidlo
(`${VAR:+"$VAR"}`). Pravidlo „čtení neexistujícího klíče = tvrdá chyba"
zůstává v plné síle.

### Odpovědi na otevřené otázky verze 0.2

1. **Stačí skupiny argumentů?** Ano, po sloučení prázdna s nevyplněním.
   Bez toho ne — viz výše.
2. **Chybí kámen se dvěma soubory na výstupu?** Ne — nechybí ani kámen
   s jedním. `workspace-pr` musel vrátit URL i příznak „vzniklo teď",
   a stačilo, aby URL vypisoval jen tehdy, když PR sám vytvořil. Jeden kanál.
3. **Chybí krok, který jen nastaví klíč?** Ano, `set` přibyl.
4. **Kolik kamenů na jeden nástroj?** `curl` dva (`get`, `send-json`),
   `jq` jeden. Skupiny argumentů to unesly.

### Odložené souborové výstupy

Verze 0.2 měla kámen se souborovým výstupem: engine rezervuje jméno, program
soubor vytvoří, engine ho eviduje a po posledním použití klíče smaže.

Po přechodu na stdin/stdout nevzniká v přepsaných workflow ani jeden takový
soubor, takže je celé odložené — a s ním rezervace jmen, evidence, statická
analýza posledního použití klíče, mazání ve `finally` i výjimka pro debug
režim. Z v1 tím vypadává celý podsystém.

Klíč `output` se tím scvrkl na jedinou legální hodnotu, a proto z formátu
zmizel úplně. `result` je vždycky standardní výstup procesu. Až budou
souborové výstupy potřeba, klíč se vrátí.

### Co přepis neověřil

- `{%STDIN%}` jako vstup prvního kroku — žádné z workflow nečte CLI stdin.

### Nález, který se zatím neimplementuje

**`foreach` neumí rozdělit řádek na sloupce.** Tabulku board × seznam × role
v `sync` je proto nutné rozepsat — pět skoro shodných čtyřkrokových bloků
z celkových 37. Únosné, ale přidání role je copy-paste. Kdyby to začalo
vadit, jde doplnit rozpad řádku podle oddělovače do víc klíčů; nic
z dnešního návrhu se tím nezahazuje.
