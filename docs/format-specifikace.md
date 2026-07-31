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
    ["--config", "%CURLRC%"],
    ["%URL%"]
  ],

  "inputs": {
    "URL":    { "required": true,  "description": "Úplná adresa včetně query stringu" },
    "CURLRC": { "required": false, "description": "Soubor s přihlašovací hlavičkou" }
  },

  "output": { "type": "text" },

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
  "args": [["%FLAGS%"], ["%FILTER%"]],

  "inputs": {
    "FLAGS":  { "required": false, "description": "Volby jq v jednom tokenu: -r, -Rs …" },
    "FILTER": { "required": true,  "description": "Program jq" }
  },
  "stdin": { "required": true },

  "output": { "type": "text" }
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
| `output` | ne | Default `{ "type": "text" }`. |
| `timeout` | ne | Sekundy. Default 60 (viz `Nette\Utils\Process`). |
| `allow_failure` | ne | Default `false` = nenulový exit code ukončí workflow. |

### Skupiny argumentů

`args` je pole polí. Výsledná příkazová řádka vznikne zploštěním skupin.

**Pravidlo:** skupina, ve které se některá `%VAR%` vyhodnotí na prázdno,
se celá vynechá.

```
CURLRC prázdný  → curl -sS --fail https://…
CURLRC vyplněný → curl -sS --fail --config /home/…/trello.curlrc https://…
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
Prvek může obsahovat víc proměnných i okolní text (`"--url=%URL%"`).

### `inputs`

```json
"inputs": {
  "VZOR":  { "required": true },
  "LIMIT": { "required": false, "default": "10" }
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

Krok ho plní stejně jako ostatní vstupy, pod jménem `STDIN`:

```json
"in": { "FILTER": ".title", "STDIN": "%TASK%" }
```

`%STDIN%` se **nikdy nedosazuje do `args`** — je to označení kanálu, ne
proměnná. (Pozor na optickou kolizi: první krok workflow může vypadat jako
`"in": { "STDIN": "%STDIN%" }`. Vlevo je kanál kamene, vpravo klíč v mapě
enginu se vstupem CLI volání. Je to správně.)

Bez `stdin` dostane proces prázdný standardní vstup.

**Stdin je hlavní cesta pro velká data.** Prompt pro agenta, task JSON,
tělo komentáře — to všechno teče stdin→stdout mezi kroky přes mapu. Soubor
se použije, jen když si ho nástroj vyžádá (viz `output.type: file`).

### `output`

```json
"output": { "type": "text" }
"output": { "type": "file", "ext": "sql", "argument": "OUTFILE" }
```

| `type` | co se uloží |
|---|---|
| `text` | standardní výstup procesu |
| `file` | cesta k temp souboru, který engine rezervoval |

U `type: file`:

- `argument` — jméno proměnné, pod kterou se cesta zpřístupní v `args`
  (`%OUTFILE%`). Nesmí kolidovat s `inputs` ani se jmenovat `STDIN`.
- `ext` — volitelná přípona. Použij, když se nástroj řídí příponou
  (`ffmpeg`, `tar`, `convert`).
- Engine soubor **nevytváří**, jen rezervuje jméno. Vytvoří ho spouštěný program.
- Engine si ho eviduje jako vlastní a smaže po posledním použití klíče,
  do kterého byla cesta uložena, **a také když se ten klíč přepíše** — i při
  pádu workflow. V debug režimu nemaže.
- Když příkaz selže a soubor nevznikne, klíč se přesto zapíše. V mapě pak
  sedí cesta k neexistujícímu souboru; to je normální stav.
- U `type: file` se standardní výstup procesu zahazuje. Chceš-li ho, přesměruj
  ho v samotném nástroji.

---

## 2. Workflow (`workflows/<jmeno>.json`)

```json
{
  "name": "deploy",
  "description": "Zjistí verzi API na daném prostředí.",

  "inputs": {
    "ENV": { "required": true,  "description": "prod | staging" },
    "TAG": { "required": false, "default": "latest" }
  },

  "steps": [
    {
      "type": "run",
      "block": "curl-get",
      "in":  { "URL": "https://api.example.com/%ENV%/status" },
      "out": { "result": "STATUS", "exit_code": "RC" }
    },
    {
      "type": "if",
      "condition": { "left": "%RC%", "op": "eq", "right": "0" },
      "then": [
        {
          "type": "run",
          "block": "jq",
          "in":  { "FLAGS": "-r", "FILTER": ".version", "STDIN": "%STATUS%" },
          "out": { "result": "VERZE" }
        }
      ],
      "else": [
        { "type": "set", "key": "VERZE", "value": "neznámá" }
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
| `in` | ne | Mapa `vstup kamene → šablona`. Klíč `STDIN` plní standardní vstup. |
| `out` | ne | Mapa `kanál → klíč v mapě enginu`. |
| `name` | ne | Popisek pro log a GUI. Nemá vliv na běh. |
| `allow_failure` | ne | Přepíše nastavení kamene pro tento krok. |
| `timeout` | ne | Přepíše nastavení kamene pro tento krok. |

Hodnoty v `in` jsou šablony — text s `%KLIC%`. Klíč, který v mapě neexistuje,
je **tvrdá chyba a konec běhu**.

`out` přijímá kanály:

| kanál | obsah |
|---|---|
| `result` | podle `output.type` kamene: text stdoutu, nebo cesta k souboru |
| `stderr` | chybový výstup |
| `exit_code` | návratový kód jako text (`"0"`) |

Krok tím nemusí vědět, jestli je kámen textový nebo souborový — kameny jdou
zaměňovat. Neuvedený kanál se zahodí; krok bez `out` mapu nemění.

### Krok `if`

| klíč | povinné | popis |
|---|---|---|
| `type` | ano | `"if"` |
| `condition` | ano | Viz níže. |
| `then` | ano | Pole kroků. Smí být prázdné. |
| `else` | ne | Pole kroků. |

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
`{"key":"X","value":"%X% a něco"}` přečte starou hodnotu a zapíše novou.

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
{ "left": "%RC%", "op": "eq", "right": "0" }
```

`left` i `right` jsou šablony. Operátory:

| op | význam |
|---|---|
| `eq`, `neq` | porovnání řetězců |
| `gt`, `gte`, `lt`, `lte` | číselné porovnání; nečíselná hodnota = chyba |
| `contains` | `left` obsahuje `right` |
| `empty`, `not_empty` | `right` se ignoruje |

---

## 3. Mapa enginu

- Klíče jsou case-sensitive. Doporučení: VELKÁ_PÍSMENA.
- **Všechny hodnoty jsou text.** Žádné pole, objekty, čísla.
- Počáteční obsah: vstupy workflow podle jmen, `STDIN` (standardní vstup
  CLI volání; prázdný řetězec, když nic nepřišlo) a `CWD`.
- Nic dalšího z prostředí neprochází. Cesty (`$HOME`, adresáře s prompty,
  konfigurace) jsou běžné vstupy workflow a přicházejí z příkazové řádky.
- Zápis do existujícího klíče ho přepíše.

### Šablonování

- Tvar `%KLIC%`. Vyžaduje oba delimitery.
- **Neznámý vzor projde beze změny.** `date +%Y` i procentové kódování v URL
  (`%20`) fungují, pokud `Y` a `20` nejsou klíče v mapě. (Proto ta velká
  a delší jména.)
- Literální procento: `%%`.
- Dosazuje se **jedním průchodem**; výsledek se dál nezpracovává, takže data
  obsahující `%NECO%` se nevyhodnocují. Tohle pravidlo je to, co dovoluje
  protahovat mapou cizí text — komentáře z Trella, výstup agenta —
  bez rizika, že se něco v datech vyhodnotí.

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
- `in` obsahuje jméno, které kámen nedeklaruje (ani `STDIN`, když kámen `stdin` nemá)
- šablona čte klíč, který **žádný krok nikdy nezapisuje** (překlep)
- šablona čte klíč, který v žádné předchozí větvi nemohl vzniknout
- podmínka nebo `foreach.over` čte klíč, který nemohl vzniknout
- `output.argument` koliduje se jménem vstupu nebo se jmenuje `STDIN`
- `%STDIN%` použito v `args`
- neznámý operátor v podmínce

**Varování**
- šablona čte klíč zapsaný jen v jedné větvi `if` nebo uvnitř `foreach`
  (může, ale nemusí existovat)
- klíč se zapisuje a nikdy nečte
- vstup workflow se nikde nepoužívá
- kámen s `output.type: file`, jehož `result` se nikam neukládá (soubor osiří)

Rozdíl mezi první a druhou odrážkou u chyb je podstatný: klíč, který nikdo
nikdy nezapisuje, je překlep a musí spadnout. Klíč zapisovaný podmíněně je
legitimní a smí projít s varováním.

---

## 6. Co ověřil přepis bashových workflow

Přepsáno: `jpw-indev-techlead`, `-developer`, `-developer-haiku`,
`-assistent`, `-assistent-haiku` (→ `card-dev` 29 kroků, `card-spec`
25 kroků) a `jpw-indev-sync` (→ `sync` 37 kroků). Počítáno včetně kroků
vnořených v `if` a `foreach`. Nad 14 kameny.
`jpw-queue-consume` zůstává v bashi — je to dohled nad procesem, ne workflow.

### Změny proti verzi 0.2

| změna | co si ji vynutilo |
|---|---|
| přibyl `foreach` | `sync` iteruje přes karty vrácené API |
| přibyl `set` | složení komentáře z výsledku agenta a odkazu na PR |
| úklid temp souboru i **při přepsání klíče** | v `foreach` se klíč přepisuje každou iterací |
| **zrušen rozdíl mezi „nevyplněno" a `""`** | viz níže |
| do mapy přibylo `CWD`, nic jiného z prostředí | cesty patří na příkazovou řádku |

### Zrušení rozdílu „nevyplněno" vs `""`

Verze 0.2 je vedla jako dvě různé věci. Přepis ukázal, že to nejde udržet:
**krok nikdy nemůže vyrobit „nevyplněnou" hodnotu.** Stdout příkazu je vždy
řetězec, takže když `repo-find` nenajde repozitář, v mapě skončí `""` —
což by byla „vyplněná" hodnota a skupina `["--repo=%REPO%"]` by nevypadla.
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
2. **Chybí kámen se dvěma soubory na výstupu?** Ne. `workspace-pr` musel
   vrátit URL i příznak „vzniklo teď", a stačilo, aby URL vypisoval jen
   tehdy, když PR sám vytvořil. Jeden kanál.
3. **Chybí krok, který jen nastaví klíč?** Ano, `set` přibyl.
4. **Kolik kamenů na jeden nástroj?** `curl` dva (`get`, `send-json`),
   `jq` jeden. Skupiny argumentů to unesly.

### Co přepis neověřil

- `output.type: file` a s ním celá evidence a úklid temp souborů. Po přechodu
  na stdin/stdout nevzniká v těchto workflow ani jeden engine-rezervovaný
  soubor. Pravidlo o mazání při přepsání klíče je tedy odvozené, ne vyzkoušené.
- `%STDIN%` jako vstup prvního kroku — žádné z workflow nečte CLI stdin.

### Nálezy, které se zatím neimplementují

**`allow_failure` je hrubší než realita.** Je to boolean, ale unixové nástroje
rozlišují víc stavů: `jptq task` vrací 1 „už je ve frontě" a jiné kódy pro
skutečné selhání, `grep` 1 „nenalezeno" a 2 „chyba", `test` a `diff` totéž.
V `sync` se proto ztrácí rozlišení, které bash měl — s `allow_failure: true`
projde i rozbitý queue soubor. Řešením by bylo `allow_exit_codes: [0, 1]`.
Zapsáno jako doložený nález, neimplementuje se.

**`foreach` neumí rozdělit řádek na sloupce.** Tabulku board × seznam × role
v `sync` je proto nutné rozepsat — pět skoro shodných čtyřkrokových bloků.
Únosné (29 kroků celkem), ale přidání role je copy-paste. Kdyby to začalo
vadit, jde doplnit rozpad řádku podle oddělovače do víc klíčů; nic
z dnešního návrhu se tím nezahazuje.
