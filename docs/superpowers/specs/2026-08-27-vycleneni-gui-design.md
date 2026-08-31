# Vyčlenění GUI do vlastního repozitáře — návrh

Datum: 2026-08-27

## Cíl

`gui/` opustí repozitář `donut-org/donut` a stane se samostatným
repozitářem a balíčkem `donut-org/donut-gui`, instalovatelným přes
`composer create-project`. Jádro zůstává knihovnou, GUI se stává
projektem, který ji požaduje jako běžnou závislost.

## Proč

Rozhodla distribuce, ne velikost adresáře. **Packagist umí publikovat jen
balíček, který má `composer.json` v kořeni repozitáře** — podadresář
publikovat neumí. Jakmile má být GUI instalovatelné přes
`composer create-project donut-org/donut-gui`, musí `gui/composer.json`
skončit v kořeni nějakého repozitáře. Zůstat jak je a zároveň být na
Packagistu nejde.

Monorepo s automatickým subtree splitem do read-only mirroru (jak to dělá
Symfony) by tuhle překážku obešlo, ale za mašinerii, kterou data nepodpírají:
z 215 commitů, které se dotkly `gui/` nebo `src/`, jich zasáhlo **obojí
zároveň jen 7**. Pro jednoho člověka je to dva stavy pravdy navíc bez
odpovídajícího zisku.

Rozdělení sedí i na to, co už dnes platí: dva composer projekty, dvě sady
závislostí (jádro `nette/utils`, GUI Nette application, latte, tracy,
forms), dvě minimální verze PHP (8.2 vs 8.3), a vazba jen jedním směrem —
GUI sahá na 24 tříd jádra, jádro o GUI neví nic.

Co **není** argumentem pro vyčlenění, i když to tak vypadá: že testy GUI
a jeho PHPStan dnes v CI neběží vůbec. Je to pravda — kořenová matice
`gui/` nespouští, takže ~50 testů a `level: max` hlídá jen ruční
spuštění — ale opravit se to dá bez rozdělení, viz „CI" níže.

## Cílový stav

| | `donut-org/donut` | `donut-org/donut-gui` |
|---|---|---|
| typ | `library` | `project` |
| PHP | `>=8.2`, matice 8.2–8.4 | `>=8.3`, matice 8.3–8.4 |
| instalace | `composer require` | `composer create-project` |
| vazba | neví o GUI | `"donut-org/donut": "^1.0"` |

Obojí vychází jako **1.0.0**: jádro jako knihovna se stabilním API, GUI
jako hotová aplikace.

## Podmínka: nejdřív v1.0.0 jádra

Vyčlenění je navázané na vydání jádra. Dnes prochází
`"donut-org/donut": "*"` v `gui/composer.json` výhradně díky path
repozitáři `{"type": "path", "url": "../"}`; poslední tag je **v0.8.0**.
Bez vydané verze nemá GUI co požadovat — publikovat ho dřív by znamenalo
vystavit balíček, který se nedá nainstalovat.

Načasování podpírá i historie: z těch sedmi křížových commitů je **pět
z 25.–27. srpna 2026** (profily, angličtina, přejmenování `result` na
`stdout`). Kříž přichází ve vlnách, když se hýbe jádro, a jedna taková
vlna právě běží. Dokud neskončí, každý křížový zásah by se změnil na
„vydej jádro, pak ho v GUI použij".

Pořadí je tedy: dojet rozdělanou vlnu → tag **v1.0.0** jádra → teprve pak
split.

## Přesun kódu

`git subtree` je v prostředí k dispozici (`git version 2.39.5`);
`git filter-repo` chybí a `filter-branch` je deprecated, ale nejsou
potřeba.

V repozitáři donutu:

```bash
git subtree split --prefix=gui -b gui-only
```

Vznikne větev, kde je obsah `gui/` v kořeni a historie jen těch commitů,
které se GUI dotkly. Do prázdného nového repozitáře:

```bash
mkdir ../donut-gui && cd ../donut-gui && git init
git remote add source ../donut
git fetch source gui-only
git checkout -b master FETCH_HEAD
git remote remove source
```

Žádná kopie, žádná ztracená historie.

**Kontrola po splitu:** `git ls-files | wc -l` musí dát **107**
(tolik sledovaných souborů má dnes `gui/`) a `git log --oneline | wc -l`
zhruba **148**.

## `composer.json` nového repa

Tři změny oproti dnešku:

1. Pryč `repositories` s `path` — nahradí ho Packagist.
2. `"donut-org/donut": "*"` → `"donut-org/donut": "^1.0"`.
3. Pryč `minimum-stability: dev` a `prefer-stable` — jsou tam dnes jen
   proto, že path repozitář hlásí verzi `dev-master`
   (`gui/composer.lock` má `"version": "dev-serializer"`). Proti
   tagovanému `^1.0` nemají důvod.

`composer.lock` **zůstává sledovaný**, jak je dnes. U balíčku typu
`project` rozdávaného přes `create-project` je to správně — uživatel
dostane reprodukovatelné prostředí. Nové `.gitignore` proto nesmí
zdědit kořenové pravidlo `composer.lock` (dnes `gui/composer.lock`
sledovaný je, i když ho to pravidlo matchuje).

## Souběžný vývoj nad jádrem i GUI

Jediné místo, kde rozdělení opravdu bolí. Dnes path repozitář ukazuje
jádro živě, včetně necommitnutých změn; po přechodu na `^1.0` by každá
změna jádra znamenala tag.

**Path repozitář jako fallback nefunguje** — ověřeno: Composer 2.10.2
na `path` repozitáři s neexistující cestou **skončí chybou**, ne tichým
přeskočením:

```
In PathRepository.php line 163:
  The `url` supplied for the path (../neexistuje) repository does not exist
```

Nechat `path` v publikovaném `composer.json` tedy nelze — rozbil by
instalaci každému, kdo nemá jádro jako sousední adresář.

Řešením je **druhý manifest** `composer-dev.json` v repozitáři GUI,
používaný jen lokálně:

```json
{
	"name": "donut-org/donut-gui",
	"repositories": [
		{"type": "path", "url": "../donut", "options": {"symlink": true}}
	],
	"require": {
		"donut-org/donut": "*",
		"…": "zbytek shodný s composer.json"
	},
	"minimum-stability": "dev",
	"prefer-stable": true
}
```

```bash
COMPOSER=composer-dev.json composer install
```

Ověřeno na zkušebním projektu: nainstaluje se symlink
`vendor/donut-org/donut → ../../../donut` a nový soubor v jádru je ve
vendoru vidět okamžitě — tedy přesně dnešní živé chování. Publikovaný
`composer.json` zůstává čistý.

Vzniká `composer-dev.lock`; patří do `.gitignore`.

**Cena, kterou to má:** seznam závislostí je na dvou místech. Přibude-li
balíček, musí do obou manifestů. Je to vědomá výměna za to, že
publikovaný `composer.json` neobsahuje nic, co platí jen na Honzově
disku.

## CI

`janpecha/actions` nabízí `nette-tester-library.yml`,
**`nette-tester-project.yml`**, `code-checker.yml`, `phpstan.yml`
a `frontbot.yml`. GUI je `type: project`, takže mu sedí
`nette-tester-project.yml`; matice PHP **8.3 a 8.4**. `phpstan.neon`
zůstává na `level: max` nad `src` a `tests`, i s `bootstrapFiles`.

**CI GUI ale nemá čekat na vyčlenění.** Obě potřebná workflow —
`nette-tester-project.yml` i `phpstan.yml` — mají vstup
`workingDirectory`, takže `gui/` jde do dnešní matice zapojit hned,
přidáním dvou jobů do `.github/workflows/build.yml`:

```yaml
    gui-tests:
        uses: janpecha/actions/.github/workflows/nette-tester-project.yml@master
        with:
            phpVersions: '["8.3", "8.4"]'
            workingDirectory: gui

    gui-static-analysis:
        uses: janpecha/actions/.github/workflows/phpstan.yml@master
        with:
            phpVersions: '["8.3"]'
            workingDirectory: gui
```

Vyčlenění je navázané na v1.0.0 jádra, které ještě není; nechat GUI do
té doby bez CI je zbytečné. Po splitu se tyhle dva joby z donutu
odstraní a v novém repu se stanou samostatným `build.yml` bez
`workingDirectory`.

## Dokumentace

Návrhové dokumenty a plány GUI jdou s ním. Rozsah je větší, než vypadá
na první pohled — nejde o jeden design a šest plánů, ale zhruba
o **dvacet dokumentů**.

**Jdou do `donut-org/donut-gui/docs/`** (deset specifikací a deset
plánů):

`gui-design`, `gui-vrstva1`, `gui-vrstva2`, `editace-kamene`,
`editace-workflow-kroky`, `obalka-workflow`, `gui-vzhled`, `gui-obsah`,
`gui-seznamy`, `gui-graf`, `krok-run-pevne-vstupy` — vždy specifikace
i odpovídající plán, pokud oba existují.

Subtree split je nepřenese; leží mimo `gui/`, takže jdou zvlášť běžným
commitem. Historii dokumentace tím ztratí, kód ne.

**Zůstávají v donutu** tři dokumenty, které patří oběma stranám,
protože všechny tři měnily jádro a GUI je jen následovalo:

| dokument | proč zůstává |
|---|---|
| `profily-a-xdg` | profily zavedlo jádro, CLI i GUI je přejaly |
| `anglicke-texty` | vlna přes hlášky CLI, výjimky i Latte šablony |
| `serializer` | motivovaný GUI, ale implementovaný v `src/Writer/` |

**Křížové odkazy se rozbijí.** 28 z 36 dokumentů cituje jiný dokument
jménem souboru a `2026-08-05-gui-design.md` je nejcitovanější v repu
(7×) — přitom odchází. Součástí přesunu je proto průchod odkazy: citace
přesunutého dokumentu se doplní o repozitář, ve kterém nově leží, a
totéž opačným směrem u dokumentů, které v novém repu odkazují zpátky do
donutu. Zejména `profily-a-xdg-design.md`, který výslovně nahrazuje
sekce v `cli-design.md` **i** v `gui-design.md`.

Tento dokument zůstává v donutu — popisuje operaci na donutu.

## Co se změní v donutu

- `git rm -r gui`
- z `.github/workflows/build.yml` odejdou joby `gui-tests`
  a `gui-static-analysis`, přidané mezitím podle sekce „CI"
- readme dostane odkaz na `donut-org/donut-gui` místo mlčení o GUI
- kořenový `.gitignore` ztratí pravidla, která platila jen pro GUI
- `gui/readme.md` dnes končí větou „CI matice donutu `gui/` zatím
  nespouští — je to jiný composer projekt a chystá se do vlastního
  repozitáře". Ta věta v novém repu neplatí a musí pryč.

## Jak poznat, že je hotovo

1. `git ls-files | wc -l` v novém repu dá 107, `git log` zhruba 148 commitů.
2. `composer install` v čistém klonu nového repa projde **bez** sousedního
   jádra na disku — to je test, že path repozitář opravdu zmizel.
3. `COMPOSER=composer-dev.json composer install` vedle checkoutu jádra
   vyrobí symlink ve `vendor/donut-org/donut`.
4. `vendor/bin/tester tests -C` i `vendor/bin/phpstan analyse` v novém
   repu projdou na nulu.
5. CI nového repa proběhne zeleně na 8.3 i 8.4.
6. V donutu `make test` a `make phpstan` projdou po odstranění `gui/`.
7. `composer create-project donut-org/donut-gui` v prázdném adresáři
   dá spustitelné GUI — ověřit `make server` proti profilu.

## Co se vědomě neřeší

- **Automatický subtree split ani mirror.** Přesun je jednorázový, ne
  průběžná synchronizace. Po splitu je nové repo jediným zdrojem pravdy
  pro GUI.
- **Sdílené CI napříč repozitáři.** Každý repozitář si testuje své.
  Že GUI proti nové verzi jádra rozbité není, ukáže až jeho vlastní
  build po zvýšení constraintu.
- **Zpětná kompatibilita cesty `gui/`.** Kdo měl checkout donutu s GUI,
  přejde na klon nového repozitáře. Přechodové období se nedělá.
- **Odstranění duplicity mezi `composer.json` a `composer-dev.json`.**
  Composer nemá nativní lokální override manifestu; alternativy
  (necommitovaná úprava `composer.json`, globální path repozitář) jsou
  křehčí než dvě místa k údržbě.
