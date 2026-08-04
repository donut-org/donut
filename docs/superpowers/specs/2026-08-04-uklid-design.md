# Úklid po CLI — návrh

Datum: 2026-08-04

## Cíl

Dodělat devět nálezů ze závěrečné revize CLI, které se vědomě neopravovaly.
Žádný nebránil používání; všechny byly skutečné. Seznam je v sekci
„Co zbývá dodělat" v `docs/superpowers/specs/2026-08-03-cli-design.md`
a tímhle dokumentem se ruší.

Referenční pravda zůstává `docs/format-specifikace.md` verze 0.3.

## Změny chování CLI

### `--list` přežije vadný soubor

Dnes se výpis zastaví na prvním souboru, který nejde načíst — a to až poté,
co se předchozí vypsala. Nejhorší kombinace: částečný výstup i selhání.

Nově jde každý úspěšně načtený workflow na stdout, každý vadný se ohlásí na
stderr, a když bylo aspoň jedno vadné, končí se kódem 2.

`--list` je poznávací příkaz. Jeden rozbitý soubor nesmí schovat ostatní —
zvlášť ne ve chvíli, kdy je adresář rozdělaný a člověk potřebuje vidět, co
vlastně má. Chyby přitom nesmí téct do stdout, aby se výpis dal dál
zpracovat.

### Hláška o neexistujícím workflow řekne, kde se hledalo

`donut` hledá `blocks/` a `workflows/` v pracovním adresáři. Je to nejostřejší
hrana celého nástroje a nejčastější příčina toho, že „workflow neexistuje".
Hláška proto uvede cestu, ve které se hledalo.

### Použití při chybě jde na stderr

Dnes se vypisuje na stdout i tehdy, když je důvodem chyba. Stdout zůstává
vyhrazený pro `--list`, `--help` a výstup kroků, které si `result`
nemapují; chyby patří na stderr.

`donut --help` bez workflow je dál stdout — to není chyba, ale dotaz.

### `--help=x`, `--list=x` a `--=x` jsou chyby

Dnes tiše propadnou do pojmenovaných hodnot. `--help` a `--list` jsou
příznaky, ne vstupy; prázdné jméno klíče není jméno. Obojí končí kódem 2,
stejně jako každý jiný nesmyslný tvar argumentu.

### Neočekávaná `Donut\Exception` se zachytí, kód 2

Dnes by proletěla jako neošetřená výjimka s návratovým kódem PHP. Žádná
taková dnes nevzniká, ale záchytný `catch` je právě pro to, co nikdo
nepředvídal.

Kód **2**, a to i když neproběhl krok — z významu „nespustilo se" je tady
podstatnější druhá polovina: **neopakuj to**. Opakovat neklasifikovanou
vnitřní chybu je marné. Hláška se odliší od běžného selhání, aby bylo
poznat, že jde o chybu nástroje, ne workflow.

## Drobnosti mimo CLI

**Validátor odmítne kámen, který deklaruje vstup jménem `stdin`.** Kolidoval
by se jménem kanálu — krok by nešlo napsat tak, aby bylo jasné, jestli plní
vstup, nebo standardní vstup. Dnes to nezakazuje nic.

**`Runner::run()` doplní `@throws CannotStartException`.** Hází ji ze tří
míst a docblock o ní mlčí.

**Přijímací test CLI připne deskriptor 0.** Dnes je bezpečný jen díky tomu,
že nette/tester zavírá zápisový konec stdin; při jiném způsobu spuštění by
se `donut` mohl na čtení stdin zaseknout.

**Srovná se zarovnání sloupců** v JSON souborech přepisu, které se rozpadlo
přejmenováním na camelCase.

## Kořenový readme

`readme.md` v kořeni dnes popisuje publikování na Twitter, Facebook
a Instagram z RSS — balíček, který tenhle projekt před 69 commity celý
zahodil.

Nahradí ho zhruba stránka: co to je, proč to není bash, instalace, **jeden
funkční příklad od začátku do konce** — kámen, workflow, spuštění a co
vyleze — a rozcestník do `docs/`.

Formát se **needuplikuje**. `docs/format-specifikace.md` existuje a druhá
pravda vedle ní by se dřív nebo později rozešla; každá změna formátu by
znamenala dvě editace a nikdo by nevěděl, která platí.

Readme výslovně uvede, že `donut` hledá `blocks/` a `workflows/`
v pracovním adresáři.

## Testy

Každá změna chování dostane test, který by bez ní spadl. Jmenovitě:

- `--list` nad adresářem s jedním vadným souborem — dobrá na stdout, vadné
  na stderr, kód 2
- hláška o neexistujícím workflow obsahuje cestu
- použití při chybě je na stderr a stdout zůstává prázdný
- `--help=x` a `--=x` končí kódem 2
- kámen se vstupem `stdin` neprojde validací

Záchytný `catch` pro `Donut\Exception` se testuje podstrčenou implementací,
která ji hodí — jinak by ta větev nešla spustit, protože ji dnes nic neháže.

## Co se vědomě nedělá

- **Dvoumezerové odsazení nadpisů** v `--list` a `--help`, které ukazuje
  ilustrace v návrhu. Závěrečná revize to zařadila jako not-worth-doing.
- **Čtení rour přes `stream_select`** v přijímacím testu. Při velikosti
  výstupu těch fixtur je sekvenční čtení bezpečné; připnutý deskriptor 0
  řeší to, co skutečně hrozilo.
