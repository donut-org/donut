# Serializér — návrh

Datum: 2026-08-06

## Cíl

Složit `Workflow` a `Block` objekty zpátky do souboru. Je to první ze tří
projektů, na které se rozpadla vrstva 3 GUI (builder).

Bez něj nejde v GUI zapisovat vůbec, takže jde první — a dá se celý ověřit
bez jediného formuláře.

Referenční pravda formátu je `docs/format-specifikace.md` verze 0.3.

## Kam patří ve vrstvě 3

Vrstva 3 podle `docs/superpowers/specs/2026-08-05-gui-design.md` je na jeden
spec příliš velká. Rozpadá se na tři projekty, každý s vlastním návrhem,
plánem a použitelným výsledkem:

1. **Serializér** — tenhle dokument. Žádné GUI.
2. **Editace kamene** — nejmenší objekt bez vnořování; ověří celou zápisovou
   cestu formulář → objekt → serializér → soubor → znovu naparsovat na tvaru,
   který se dá udržet v hlavě.
3. **Editace workflow** — hlavička, vstupy, kroky (přidat, upravit, přesunout,
   smazat). Největší kus; až se ukáže jeho skutečná velikost, může se ještě
   rozpůlit.

Pořadí není libovolné: serializér musí být první, kámen před workflow.

## Co vzniká

`Donut\Writer\WorkflowWriter` a `Donut\Writer\BlockWriter`, symetricky
k `Donut\Parser\WorkflowParser` a `BlockParser`.

Každý umí dvě věci:

- složit objekt do pole
- zapsat ho do souboru přes `Nette\Utils\Json::encode($data, Json::PRETTY)`

**GUI o JSONu neví.** Dostane objekt, předá objekt; kódování i zápis dělá
donut, protože objekty definuje donut.

### Cestu dostane, neodvozuje ji

Zapisovač bere cestu k souboru jako parametr.

Původně měl cestu odvozovat ze jména (`<adresář>/<name>.json`). To je horší:
cestu ze jména dnes skládá **jediné místo v celém repozitáři**
(`src/Cli/Application.php`), všude jinde to jde opačným směrem — `glob()`
najde soubory, `basename()` z nich vytáhne jména. `BlockRepository`
i `WorkflowRepository` si tak staví mapu jméno → cesta, takže pro každé jméno,
které znají, tu cestu už drží. Odvozování v zapisovači by bylo druhým výkladem
téhož pravidla, který se musí shodnout s tím, jak repository soubory našly.

Bezpečnost se tím neztrácí, jen se řeší kontrolou místo konstrukce:
**zapisovač ověří, že `basename($path, '.json')` odpovídá `$workflow->name`,
a jinak vyhodí výjimku.** Je to totéž pravidlo, které parser vynucuje při
čtení, takže se čtení a zápis shodnou z definice.

## Formát výstupu

`Nette\Utils\Json::encode($data, Json::PRETTY)`, bez vlastního formátovače.

Cena je změřená a je nerovnoměrná. Patnáct z devatenácti souborů referenční
zátěže je dnes v podstatě v tom tvaru, který `PRETTY` vyrábí — změní se jim
jen šířka odsazení. Čtyři jsou psané kompaktně a jsou mezi nimi obě velká
workflow:

| soubor | dnes | po uložení | |
|---|---|---|---|
| `card-dev.json` | 170 | 353 | +108 % |
| `sync.json` | 241 | 435 | +80 % |
| `repo-check.json` | 31 | 51 | +65 % |
| `echo.json` | 13 | 15 | |

Ta úspora není náhoda: `card-dev` drží 29 kroků na 170 řádcích tím, že píše
`"type": "run", "name": "…"` na jedné řádce a mapy `in`/`out` po jedné řádce
na položku. Po uložení to bude platné, ale delší, a skupina argumentů přestane
být vidět jako skupina.

**Přijímá se vědomě** — vlastní formátovač by znamenal víc kódu a vlastní testy
kvůli něčemu, co se dá přečíst i rozepsané.

## Která pole se vypisují

Podle toho, co je v souborech dnes:

- **`required` vždycky** — je vypsané u všech 57 vstupů referenční zátěže,
  nikde vynechané.
- **Zbytek jen když je vyplněný** — `description`, `default`, `timeout`,
  `allow_failure`, `stdin`, `name`.

Tím se soubor při uložení změní co nejmíň.

## Testy

Round-trip **objekt → pole → objekt**, porovnání přes `==`. Ověřeno, že to na
těchhle objektech funguje strukturálně, včetně `Template` (drží `source`
i rozparsované segmenty a `==` je porovná).

Směr „soubor → objekt → soubor" by byl přísnější, ale zakázal by jakoukoliv
normalizaci — a ta se právě přijala.

### Dvě fixtury, ne jedna

Tohle je jádro návrhu.

**1. Referenční zátěž** — 15 kamenů a 4 workflow z `docs/workflows/donut/`.
Ověří skutečné tvary, které se doopravdy používají.

**2. Syntetická fixtura, která vyplní každé volitelné pole.**

Sama referenční zátěž **nestačí** a je to změřené. Pět skutečných volitelných
polí se v těch devatenácti souborech nevyskytuje ani jednou:

| pole | výskytů v referenční zátěži |
|---|---|
| `default` u vstupu kamene | 0 |
| `default` u vstupu workflow | 0 |
| `timeout` u kroku | 0 |
| `allow_failure` u kroku | 0 |
| `name` u kroku `set` | 0 |

Zapisovač, který kterékoliv z nich tiše zahodí, by round-trip nad referenční
zátěží prošel bez jediné chyby. To je přesně to riziko, které pojmenoval návrh
vrstev — „tiché zahození pole, na které serializér zapomene" — a nejzřejmější
test ho nechytí.

Syntetická fixtura se proto odvozuje **z parseru, ne ze zátěže**: co parser
umí přečíst, to musí fixtura obsahovat. Pokrýt musí i tvary, které zátěž má
jen náhodou — prázdnou větev `else`, `condition` bez `right`, vnořený
`foreach` v `if`.

### Co round-trip neověří

Že soubor na disku jde znovu načíst. Objektové porovnání běží v paměti;
souborová cesta (kódování, zápis, kontrola jména) potřebuje vlastní test:
zapsat do dočasného adresáře a naparsovat zpátky.

## Souběh se neřeší

GUI čte při každém requestu, ale mezi vykreslením formuláře a uložením může
soubor na disku změnit ruční editace. GUI ho přepíše bez varování.

**Vědomé rozhodnutí** — je to lokální nástroj pro jednoho člověka a soubory
jsou v gitu, takže se ztracená úprava dá vrátit. Detekce přes otisk souboru by
znamenala kód navíc a falešné poplachy při uložení beze změny.

## Co se vědomě nedělá

- **Vlastní formátovač.** Viz „Formát výstupu".
- **Odvozování cesty ze jména.** Viz „Cestu dostane, neodvozuje ji".
- **Detekce souběhu.** Viz výše.
- **Zápis odkudkoliv jinud než z GUI.** `Runner` ani CLI zapisovač nepotřebují
  a nedostanou ho — zadání říká, že engine soubory nevytváří.
