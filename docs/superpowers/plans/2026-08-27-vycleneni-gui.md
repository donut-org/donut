# Vyčlenění GUI do vlastního repozitáře — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Přesunout `gui/` z repozitáře `donut-org/donut` do samostatného repozitáře a balíčku `donut-org/donut-gui`, instalovatelného přes `composer create-project`.

**Architecture:** Kód se přenese `git subtree split --prefix=gui`, takže si vezme svých 148 commitů historie. V novém repu nahradí path repozitář tagovaný constraint `"donut-org/donut": "^1.0"`; souběžný vývoj nad jádrem obstará druhý manifest `composer-dev.json`. Dokumentace GUI jde s ním, křížové odkazy se opraví na pět konkrétních míst.

**Tech Stack:** PHP 8.3+, Composer 2, Nette (application, bootstrap, forms, latte), Tracy, Nette Tester, PHPStan, GitHub Actions (`janpecha/actions`).

**Spec:** `docs/superpowers/specs/2026-08-27-vycleneni-gui-design.md`

## Global Constraints

- Jádro `donut-org/donut`: `type: library`, PHP `>=8.1`, matice 8.1–8.4.
- GUI `donut-org/donut-gui`: `type: project`, PHP `>=8.3`, matice 8.3–8.4.
- Obojí vychází jako verze **1.0.0**.
- `composer.lock` GUI **zůstává sledovaný** — u `type: project` rozdávaného přes `create-project` dostane uživatel reprodukovatelné prostředí.
- PHPStan `level: max`, nula chyb, nad `src` a `tests`.
- **Commity a kód anglicky, dokumentace česky.** Toto je konvence repozitáře (viz `docs/superpowers/specs/2026-08-26-anglicke-texty-design.md`) — platí i pro nové repo.
- Nesledované soubory `rss`, `docs/logo.png` a `.github/workflows/frontbot.yml` do commitů **nepatří**. Nikdy `git add -A`; vždy vyjmenovat soubory.

---

### Task 1: Zapojit `gui/` do CI současného repozitáře

Tento task je nezávislý na vyčlenění a provádí se **hned**. Vyčlenění čeká na v1.0.0 jádra; nechat do té doby ~50 testů GUI a jeho `level: max` bez CI je zbytečné.

**Files:**
- Modify: `.github/workflows/build.yml`

**Interfaces:**
- Consumes: nic.
- Produces: joby `gui-tests` a `gui-static-analysis`, které Task 7 zase odstraní.

- [ ] **Step 1: Ověřit, že reusable workflow pro `type: project` existuje**

Spec na něm stojí. Ověř, ať se nestaví na domněnce:

```bash
git ls-remote https://github.com/janpecha/actions HEAD
rm -rf /tmp/actions-check && git clone -q --depth 1 https://github.com/janpecha/actions.git /tmp/actions-check
ls -1 /tmp/actions-check/.github/workflows/
```

Expected: ve výpisu je `nette-tester-project.yml` a `phpstan.yml`.

- [ ] **Step 2: Ověřit, že obě workflow mají vstup `workingDirectory`**

Bez něj celý task nefunguje.

```bash
grep -A2 'workingDirectory:' /tmp/actions-check/.github/workflows/nette-tester-project.yml
grep -A2 'workingDirectory:' /tmp/actions-check/.github/workflows/phpstan.yml
```

Expected: obojí ukáže `type: string` a `default: '.'`.

- [ ] **Step 3: Ověřit, že testy a PHPStan GUI lokálně procházejí**

Pokud padají už teď, CI to jen zviditelní a nesmí se to plést s chybou zapojení.

```bash
cd gui && composer install && vendor/bin/tester tests -C ; vendor/bin/phpstan analyse
```

Expected: testy OK, PHPStan `[OK] No errors`. Kdyby ne — zastav se a nahlas to; oprava patří před tento task.

- [ ] **Step 4: Přidat oba joby do `build.yml`**

Do `.github/workflows/build.yml` za stávající job `static-analysis`:

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

- [ ] **Step 5: Ověřit syntaxi YAML**

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/build.yml')); print('YAML OK')"
```

Expected: `YAML OK`.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/build.yml
git commit -m "Run the GUI test suite and PHPStan in CI"
```

- [ ] **Step 7: Ověřit zelený build**

Po pushi zkontroluj běh na GitHubu. Expected: joby `gui-tests` (8.3, 8.4) a `gui-static-analysis` proběhnou zeleně. Dokud nejsou zelené, další tasky nezačínej — od Tasku 3 už se `gui/` stěhuje a rozbité testy by se hledaly ve špatném repu.

---

## ⛔ Brána: dál až po vydání v1.0.0 jádra

Tasky 2–8 se **nesmí** začít dřív, než jádro dostane tag `v1.0.0`. Důvod je tvrdý: dnešní `"donut-org/donut": "*"` prochází výhradně díky path repozitáři `{"type": "path", "url": "../"}`. Bez vydané verze nemá nový balíček co požadovat a šlo by publikovat něco, co se nedá nainstalovat. Poslední tag je **v0.8.0**.

Navíc z 7 commitů, které se dotkly `gui/` i `src/`, je 5 z 25.–27. srpna 2026 — jedna taková vlna právě běží a po rozdělení by každý její zásah znamenal „vydej jádro, pak ho v GUI použij".

---

### Task 2: Vydat v1.0.0 jádra

**Files:**
- Modify: žádný soubor kódu; jde o tag a release.

**Interfaces:**
- Consumes: nic.
- Produces: tag `v1.0.0` a balíček na Packagistu, na který se v Tasku 4 odkáže `"^1.0"`.

- [ ] **Step 1: Ověřit, že rozdělaná vlna je dokončená**

```bash
git status --short
git log --oneline -10
```

Expected: čistý strom (kromě známých nesledovaných `rss`, `docs/logo.png`, `.github/workflows/frontbot.yml`). Rozhodnutí, že se API jádra už nehýbe, je **Honzovo** — tento krok je brána, ne mechanická operace.

- [ ] **Step 2: Ověřit, že celý repozitář je zelený**

```bash
make test && make phpstan
cd gui && vendor/bin/tester tests -C && vendor/bin/phpstan analyse
```

Expected: obojí bez chyb.

- [ ] **Step 3: Otagovat a vydat**

```bash
git tag v1.0.0
git push origin v1.0.0
```

- [ ] **Step 4: Ověřit, že Packagist verzi vidí**

```bash
composer show donut-org/donut --all 2>/dev/null | grep -A3 versions
```

Expected: mezi verzemi je `v1.0.0`. Packagist se aktualizuje přes webhook; pokud verze chybí, počkej nebo spusť update ručně v jeho rozhraní. **Dokud verzi nevidí, Task 4 selže.**

---

### Task 3: Split kódu a založení nového repozitáře

**Files:**
- Create: nový repozitář `donut-org/donut-gui` (lokálně `../donut-gui`)
- Modify: nic v donutu (větev `gui-only` je pomocná)

**Interfaces:**
- Consumes: tag `v1.0.0` z Tasku 2.
- Produces: repozitář `../donut-gui` s obsahem dnešního `gui/` v kořeni a jeho historií. Tasky 4–6 pracují v něm.

- [ ] **Step 1: Zaznamenat kontrolní čísla PŘED splitem**

Bez nich se po splitu nedá poznat, že se nic neztratilo.

```bash
git ls-files gui | wc -l      # ocekavano: 107
git log --oneline -- gui | wc -l   # ocekavano: 148
```

Expected: `107` a `148`. Pokud čísla nesedí, repozitář se mezitím pohnul — použij nová čísla, ale zapiš si je.

- [ ] **Step 2: Vyrobit větev splitu**

```bash
git subtree split --prefix=gui -b gui-only
```

Expected: vypíše hash commitu. `git subtree` je součástí gitu 2.39.5; pozor, `git subtree --help` selže na chybějícím manuálu, ale příkaz funguje.

- [ ] **Step 3: Ověřit obsah větve splitu**

```bash
git ls-tree --name-only gui-only | head
git ls-tree -r --name-only gui-only | wc -l
```

Expected: v kořeni větve jsou `composer.json`, `src`, `tests`, `www`, `Makefile`, `readme.md` — **ne** adresář `gui`. Počet souborů `107`.

- [ ] **Step 4: Založit nový lokální repozitář z větve**

```bash
mkdir ../donut-gui && cd ../donut-gui && git init
git remote add source ../donut
git fetch source gui-only
git checkout -b master FETCH_HEAD
git remote remove source
```

- [ ] **Step 5: Ověřit, že historie i soubory dorazily**

```bash
git ls-files | wc -l        # ocekavano: 107
git log --oneline | wc -l   # ocekavano: 148
git log --oneline -3
```

Expected: `107`, `148`, a poslední commity odpovídají posledním změnám GUI. **Kdyby počet souborů nesouhlasil, nepokračuj** — něco se ztratilo.

- [ ] **Step 6: Uklidit pomocnou větev v donutu**

```bash
cd ../donut && git branch -D gui-only
```

- [ ] **Step 7: Založit vzdálený repozitář a pushnout**

```bash
cd ../donut-gui
gh repo create donut-org/donut-gui --public --source=. --remote=origin --description "Authoring environment for Donut workflows and blocks"
git push -u origin master
```

Expected: repozitář vznikne a master se pushne se 148 commity.

---

### Task 4: Závislost na vydaném jádru místo path repozitáře

**Files:**
- Modify: `composer.json` (v `../donut-gui`)
- Create: `composer-dev.json`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: tag `v1.0.0` (Task 2), repozitář z Tasku 3.
- Produces: `composer.json` instalovatelný bez sousedního jádra; `composer-dev.json` pro souběžný vývoj.

- [ ] **Step 1: Nejdřív ověřit, proč path repo nemůže zůstat jako fallback**

Ať je jasné, že to není opatrnost, ale nutnost. Composer na neexistující cestě **spadne**:

```bash
mkdir -p /tmp/pathtest && cd /tmp/pathtest
printf '{\n\t"name": "test/app",\n\t"repositories": [{"type": "path", "url": "../neexistuje"}],\n\t"require": {}\n}\n' > composer.json
composer install --no-interaction 2>&1 | grep -i 'does not exist'
```

Expected: `The `url` supplied for the path (../neexistuje) repository does not exist`. Proto path repozitář v publikovaném `composer.json` **být nesmí**.

- [ ] **Step 2: Přepsat `composer.json` do publikovatelné podoby**

Tři změny: pryč `repositories`, `"*"` → `"^1.0"`, pryč `minimum-stability` a `prefer-stable` (byly tam jen kvůli tomu, že path repo hlásí `dev-master`). Přibývají `authors` a `funding`, aby publikovaný balíček odpovídal jádru.

```json
{
	"name": "donut-org/donut-gui",
	"description": "Authoring environment for Donut workflows and blocks",
	"license": "BSD-3-Clause",
	"type": "project",
	"authors": [
		{
			"name": "Jan Pecha",
			"homepage": "https://www.janpecha.cz/"
		}
	],
	"funding": [
		{"type": "other", "url": "https://www.janpecha.cz/donate/"}
	],
	"require": {
		"php": ">=8.3",
		"donut-org/donut": "^1.0",
		"nette/application": "^3.2",
		"nette/bootstrap": "^3.2",
		"latte/latte": "^3.0",
		"tracy/tracy": "^2.12",
		"nette/forms": "^3.2"
	},
	"require-dev": {
		"nette/tester": "^2.6",
		"phpstan/phpstan": "^2.2"
	},
	"autoload": {
		"psr-4": {"Donut\\Gui\\": "src/"}
	}
}
```

- [ ] **Step 3: Vytvořit `composer-dev.json`**

Stejný obsah, ale s path repem a volným constraintem. Duplicita seznamu závislostí je vědomá cena — přibude-li balíček, musí do obou.

```json
{
	"name": "donut-org/donut-gui",
	"description": "Authoring environment for Donut workflows and blocks",
	"license": "BSD-3-Clause",
	"type": "project",
	"repositories": [
		{"type": "path", "url": "../donut", "options": {"symlink": true}}
	],
	"require": {
		"php": ">=8.3",
		"donut-org/donut": "*",
		"nette/application": "^3.2",
		"nette/bootstrap": "^3.2",
		"latte/latte": "^3.0",
		"tracy/tracy": "^2.12",
		"nette/forms": "^3.2"
	},
	"require-dev": {
		"nette/tester": "^2.6",
		"phpstan/phpstan": "^2.2"
	},
	"autoload": {
		"psr-4": {"Donut\\Gui\\": "src/"}
	},
	"minimum-stability": "dev",
	"prefer-stable": true
}
```

- [ ] **Step 4: Doplnit `.gitignore`**

Nové repo musí pokrýt i to, co dřív dědilo z kořene donutu — ale **`composer.lock` mezi ignorovanými být nesmí**.

```
/vendor
/temp
/tests/tmp
/log
/composer-dev.lock
```

- [ ] **Step 5: Ověřit, že publikovaný manifest je platný a nainstalovatelný BEZ jádra vedle**

Tohle je hlavní test tasku — čistý klon na stroji, kde žádný `../donut` není.

```bash
composer validate --no-check-publish
rm -rf /tmp/cleanclone && git clone -q . /tmp/cleanclone && cd /tmp/cleanclone
composer install --no-interaction 2>&1 | tail -5
ls -d vendor/donut-org/donut && cat vendor/donut-org/donut/composer.json | grep '"name"'
```

Expected: `composer validate` projde; instalace stáhne `donut-org/donut` z Packagistu (**ne** symlink) a adresář `vendor/donut-org/donut` je skutečný adresář. Ověř: `test -L vendor/donut-org/donut && echo SYMLINK || echo REALNY` musí dát `REALNY`.

- [ ] **Step 6: Ověřit, že dev režim vyrobí symlink na sousední jádro**

```bash
cd ../donut-gui
COMPOSER=composer-dev.json composer install --no-interaction 2>&1 | tail -3
test -L vendor/donut-org/donut && echo SYMLINK || echo REALNY
```

Expected: ve výstupu `Symlinking from ../donut` a kontrola vypíše `SYMLINK`.

- [ ] **Step 7: Ověřit, že symlink je opravdu živý**

```bash
touch ../donut/src/ZkouskaZiveho.php
ls vendor/donut-org/donut/src/ZkouskaZiveho.php && rm ../donut/src/ZkouskaZiveho.php
```

Expected: soubor je přes symlink vidět okamžitě. Nezapomeň ho smazat.

- [ ] **Step 8: Vrátit se do publikovaného režimu a spustit testy**

```bash
rm -rf vendor && composer install --no-interaction
vendor/bin/tester tests -C
vendor/bin/phpstan analyse
```

Expected: testy procházejí proti **vydanému** jádru 1.0.0 a PHPStan hlásí `[OK] No errors`. Tohle je skutečné ověření, že GUI na tagované verzi jádra funguje.

- [ ] **Step 9: Commit**

```bash
git add composer.json composer-dev.json composer.lock .gitignore
git commit -m "Depend on the released core instead of a path repository"
```

---

### Task 5: CI nového repozitáře

**Files:**
- Create: `.github/workflows/build.yml` (v `../donut-gui`)

**Interfaces:**
- Consumes: instalovatelný `composer.json` z Tasku 4.
- Produces: zelený build, na který se spoléhá Task 8 při publikaci.

- [ ] **Step 1: Vytvořit workflow**

Stejná sestava jako u jádra, jen matice 8.3/8.4 a bez `workingDirectory` — GUI je teď v kořeni.

```yaml
name: Build

on:
  push:
    branches:
      - master
    tags:
      - v*

  pull_request:

jobs:
    tests:
        uses: janpecha/actions/.github/workflows/nette-tester-project.yml@master
        with:
            phpVersions: '["8.3", "8.4"]'

    coding-style:
        uses: janpecha/actions/.github/workflows/code-checker.yml@master

    static-analysis:
        uses: janpecha/actions/.github/workflows/phpstan.yml@master
        with:
            phpVersions: '["8.3"]'
```

- [ ] **Step 2: Ověřit syntaxi**

```bash
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/build.yml')); print('YAML OK')"
```

Expected: `YAML OK`.

- [ ] **Step 3: Commit a push**

```bash
git add .github/workflows/build.yml
git commit -m "Add the build workflow"
git push
```

- [ ] **Step 4: Ověřit zelený build**

Expected: `tests` na 8.3 i 8.4, `coding-style` a `static-analysis` zeleně. Kdyby `coding-style` hlásil nálezy, oprav je v samostatném commitu — z donutu se přenesla nastavení, která tenhle job dosud na `gui/` nikdy nespustil.

---

### Task 6: Dokumentace nového repozitáře

**Files:**
- Create: `docs/superpowers/specs/` a `docs/superpowers/plans/` (v `../donut-gui`) — 20 souborů
- Modify: `readme.md` (v `../donut-gui`)

**Interfaces:**
- Consumes: repozitář z Tasku 3.
- Produces: dokumentaci GUI v novém repu; Task 7 se spoléhá, že tytéž soubory z donutu zmizí.

- [ ] **Step 1: Přenést deset specifikací**

Subtree split je nepřenesl — leží mimo `gui/`. Historii tím ztratí, kód ne.

```bash
mkdir -p docs/superpowers/specs docs/superpowers/plans
for f in 2026-08-05-gui-design 2026-08-06-gui-vrstva2-design \
         2026-08-13-editace-kamene-design 2026-08-14-editace-workflow-kroky-design \
         2026-08-15-obalka-workflow-design 2026-08-16-gui-vzhled-design \
         2026-08-17-gui-obsah-design 2026-08-17-gui-seznamy-design \
         2026-08-21-gui-graf-design 2026-08-27-krok-run-pevne-vstupy-design; do
	cp ../donut/docs/superpowers/specs/$f.md docs/superpowers/specs/
done
ls -1 docs/superpowers/specs | wc -l
```

Expected: `10`.

- [ ] **Step 2: Přenést deset plánů**

```bash
for f in 2026-08-05-gui-vrstva1 2026-08-06-gui-vrstva2 \
         2026-08-13-editace-kamene 2026-08-14-editace-workflow-kroky \
         2026-08-15-obalka-workflow 2026-08-16-gui-vzhled \
         2026-08-17-gui-obsah 2026-08-17-gui-seznamy \
         2026-08-21-gui-graf 2026-08-27-krok-run-pevne-vstupy; do
	cp ../donut/docs/superpowers/plans/$f.md docs/superpowers/plans/
done
ls -1 docs/superpowers/plans | wc -l
```

Expected: `10`.

- [ ] **Step 3: Opravit dva odkazy, které nově míří přes hranici repozitářů**

Oba ukazují na `serializer-design.md`, který **zůstává v donutu** (je implementovaný v `src/Writer/`).

V `docs/superpowers/specs/2026-08-05-gui-design.md`, řádek ~160:

```
1. **Serializér** — `2026-08-06-serializer-design.md`. Žádné GUI.
```
→
```
1. **Serializér** — `2026-08-06-serializer-design.md` v repozitáři
   `donut-org/donut`. Žádné GUI.
```

V `docs/superpowers/specs/2026-08-13-editace-kamene-design.md`, řádek ~15:

```
Serializér, na kterém to stojí, popisuje
`2026-08-06-serializer-design.md`.
```
→
```
Serializér, na kterém to stojí, popisuje
`2026-08-06-serializer-design.md` v repozitáři `donut-org/donut`.
```

- [ ] **Step 4: Ověřit, že jiný odkaz přes hranici nezůstal**

```bash
grep -rlE '20[0-9]{2}-[0-9]{2}-[0-9]{2}-[a-z0-9-]+\.md' docs | while read -r f; do
  grep -ohE '20[0-9]{2}-[0-9]{2}-[0-9]{2}-[a-z0-9-]+\.md' "$f" | sort -u | while read -r ref; do
    ls docs/superpowers/specs/"$ref" docs/superpowers/plans/"$ref" >/dev/null 2>&1 || echo "$f -> $ref"
  done
done
```

Expected: vypíše **jen** dva řádky se `2026-08-06-serializer-design.md` — tedy ty, které Step 3 opatřil jménem repozitáře. Cokoli dalšího je nedodělaný odkaz.

- [ ] **Step 5: Opravit `readme.md`**

Poslední odstavec dnes tvrdí něco, co nově neplatí. Odstraň větu:

```
CI matice donutu `gui/` zatím nespouští — je to jiný composer projekt
a chystá se do vlastního repozitáře.
```

a odstavec o testech uprav — `gui/` už není podadresář:

```
`gui/` je samostatný composer projekt (viz `gui/composer.json`), testy
a PHPStan se proto spouští z `gui/`, ne z kořene repozitáře:
```
→
```
Testy a statická analýza se spouští z kořene repozitáře:
```

Uprav i odkaz na návrhový dokument v úvodu readme — návrh je nově v tomto repu, v `docs/superpowers/specs/2026-08-05-gui-design.md`, ne v `donut-org/donut`.

- [ ] **Step 6: Doplnit do readme, jak se pracuje nad oběma repy zároveň**

Za sekci „Instalace" přidej:

````markdown
### Vývoj proti rozpracovanému jádru

`composer.json` požaduje vydané jádro. Při souběžné práci na jádru
i GUI použij druhý manifest, který si vezme sousední checkout donutu:

```bash
COMPOSER=composer-dev.json composer install
```

Nainstaluje `donut-org/donut` symlinkem z `../donut`, takže změny
v jádru jsou vidět okamžitě. Přibude-li závislost, musí se zapsat do
`composer.json` i `composer-dev.json`.
````

- [ ] **Step 7: Commit**

```bash
git add docs readme.md
git commit -m "Move the GUI design documents in and describe the two-repository workflow"
```

---

### Task 7: Úklid donutu

**Files:**
- Delete: `gui/` (107 souborů), 20 dokumentů z `docs/superpowers/`
- Modify: `.github/workflows/build.yml`, `readme.md`, `.gitignore`, `docs/superpowers/specs/2026-08-06-serializer-design.md`, `docs/superpowers/specs/2026-08-25-profily-a-xdg-design.md`

**Interfaces:**
- Consumes: potvrzení z Tasku 6, že dokumentace i kód jsou v novém repu.
- Produces: donut bez GUI.

- [ ] **Step 1: Ověřit, že nové repo je opravdu hotové**

Mazat se smí až po tomhle. Nové repo musí být pushnuté a zelené.

```bash
cd ../donut-gui && git status --short && git log origin/master..HEAD --oneline
```

Expected: čistý strom a **žádný** nepushnutý commit.

- [ ] **Step 2: Odstranit `gui/`**

```bash
cd ../donut
git rm -r --quiet gui
git status --short | head
```

Expected: 107 smazaných souborů.

- [ ] **Step 3: Odstranit dokumentaci GUI**

```bash
cd docs/superpowers
git rm --quiet specs/2026-08-05-gui-design.md specs/2026-08-06-gui-vrstva2-design.md \
  specs/2026-08-13-editace-kamene-design.md specs/2026-08-14-editace-workflow-kroky-design.md \
  specs/2026-08-15-obalka-workflow-design.md specs/2026-08-16-gui-vzhled-design.md \
  specs/2026-08-17-gui-obsah-design.md specs/2026-08-17-gui-seznamy-design.md \
  specs/2026-08-21-gui-graf-design.md specs/2026-08-27-krok-run-pevne-vstupy-design.md
git rm --quiet plans/2026-08-05-gui-vrstva1.md plans/2026-08-06-gui-vrstva2.md \
  plans/2026-08-13-editace-kamene.md plans/2026-08-14-editace-workflow-kroky.md \
  plans/2026-08-15-obalka-workflow.md plans/2026-08-16-gui-vzhled.md \
  plans/2026-08-17-gui-obsah.md plans/2026-08-17-gui-seznamy.md \
  plans/2026-08-21-gui-graf.md plans/2026-08-27-krok-run-pevne-vstupy.md
cd ../..
```

Zůstávají `profily-a-xdg`, `anglicke-texty` a `serializer` — všechny tři měnily jádro a GUI je jen následovalo.

- [ ] **Step 4: Opravit tři odkazy na přesunutý `gui-design.md`**

V `docs/superpowers/specs/2026-08-06-serializer-design.md`, řádek ~17:

```
Vrstva 3 podle `docs/superpowers/specs/2026-08-05-gui-design.md` je na jeden
```
→
```
Vrstva 3 podle `2026-08-05-gui-design.md` v repozitáři `donut-org/donut-gui`
je na jeden
```

V `docs/superpowers/specs/2026-08-25-profily-a-xdg-design.md`, řádky ~11–13:

```
Nahrazuje sekci „Kde hledá kameny a workflow" v
`2026-08-03-cli-design.md` a rozhodnutí „server běží v tom pracovním
adresáři, ze kterého ho někdo spustil" v `2026-08-05-gui-design.md`.
```
→
```
Nahrazuje sekci „Kde hledá kameny a workflow" v
`2026-08-03-cli-design.md` a rozhodnutí „server běží v tom pracovním
adresáři, ze kterého ho někdo spustil" v `2026-08-05-gui-design.md`
(nově v repozitáři `donut-org/donut-gui`).
```

Třetí výskyt je v `docs/superpowers/specs/2026-08-27-vycleneni-gui-design.md` — tam se `gui-design.md` **jen jmenuje** jako nejcitovanější dokument, není to odkaz ke čtení. Nechej ho být.

- [ ] **Step 5: Odstranit joby GUI z CI**

Z `.github/workflows/build.yml` smaž oba joby přidané Taskem 1: `gui-tests` a `gui-static-analysis`.

- [ ] **Step 6: Uklidit `.gitignore`**

Kořenový `.gitignore` obsahuje `composer.lock` (bez lomítka), což dosud matchovalo i `gui/composer.lock`. Ten je pryč, pravidlo ale platí pro kořen a **zůstává**. Zkontroluj, že v souboru nezůstalo pravidlo mířící jen do `gui/`:

```bash
grep -n gui .gitignore || echo "zadne pravidlo pro gui"
```

Expected: `zadne pravidlo pro gui`.

- [ ] **Step 7: Doplnit odkaz na GUI do readme**

V `readme.md` do tabulky v sekci „Documentation" přidej řádek:

```markdown
| [`donut-org/donut-gui`](https://github.com/donut-org/donut-gui) | authoring environment for workflows and blocks |
```

- [ ] **Step 8: Ověřit, že donut bez GUI stojí**

```bash
make test && make phpstan
grep -rn 'Donut\\Gui' src bin tests || echo "zadna zavislost na GUI"
```

Expected: testy i PHPStan bez chyb, a `zadna zavislost na GUI` — jádro o GUI nikdy nevědělo, tohle to potvrdí.

- [ ] **Step 9: Commit**

Nikdy `git add -A` — svezl by nesledované `rss`, `docs/logo.png` a `frontbot.yml`.

```bash
git add -u
git add readme.md .github/workflows/build.yml
git status --short
git commit -m "Move the GUI out into its own repository"
```

Před commitem zkontroluj výpis `git status --short`: `rss`, `docs/logo.png` a `.github/workflows/frontbot.yml` musí zůstat jako `??`.

---

### Task 8: Publikace na Packagistu

**Files:**
- Modify: `readme.md` (v `../donut-gui`) — odznaky

**Interfaces:**
- Consumes: zelený build z Tasku 5, hotový úklid z Tasku 7.
- Produces: `composer create-project donut-org/donut-gui` funguje.

- [ ] **Step 1: Otagovat v1.0.0**

```bash
cd ../donut-gui
git tag v1.0.0 && git push origin v1.0.0
```

- [ ] **Step 2: Zaregistrovat balíček na Packagistu**

Přidej `https://github.com/donut-org/donut-gui` na packagist.org a zapni webhook (u jádra už funguje).

- [ ] **Step 3: Ověřit instalaci z Packagistu — hlavní test celého projektu**

```bash
rm -rf /tmp/gui-install && composer create-project donut-org/donut-gui /tmp/gui-install --no-interaction
cd /tmp/gui-install && ls src www Makefile composer.json
test -L vendor/donut-org/donut && echo CHYBA-SYMLINK || echo OK-REALNY
```

Expected: projekt se stáhne a rozbalí, jádro je **reálný adresář**, ne symlink. Kdyby to hlásilo `CHYBA-SYMLINK`, v publikovaném `composer.json` zůstal path repozitář.

- [ ] **Step 4: Ověřit, že stažené GUI běží**

```bash
cd /tmp/gui-install && make server home=$HOME/.config/donut profile=default
```

Expected: server naběhne na `127.0.0.1:8000`, stránka ukáže seznam workflow a v hlavičce jméno profilu. Tohle je jediný krok, který ověří celý řetěz od Packagistu po běžící aplikaci.

- [ ] **Step 5: Doplnit odznaky do readme**

Po vzoru jádra přidej na začátek `readme.md` nového repa odznaky Build, Downloads, Latest Stable Version a License, s cestami na `donut-org/donut-gui`.

- [ ] **Step 6: Commit**

```bash
cd ../donut-gui
git add readme.md
git commit -m "Add the status badges" && git push
```
