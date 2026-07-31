# Přepis workflow do formátu donut

Ověřovací přepis existujících bashových workflow z `../jpw/` do formátu
popsaného v `../../format-specifikace.md`. Slouží k ověření formátu —
engine, který by to spustil, zatím neexistuje.

Návrh a jeho zdůvodnění: `../../superpowers/specs/2026-07-31-prepis-workflow-design.md`

```
blocks/      14 kamenů — curl a jq obslouží většinu, zbytek jsou
             vlastní příkazy odvozené z olw-* skriptů
workflows/   card-dev   (29 kroků) ← jpw-indev-developer, -developer-haiku,
                                      -assistent, -assistent-haiku
             card-spec  (25 kroků) ← jpw-indev-techlead
             sync       (37 kroků) ← jpw-indev-sync
```

`jpw-queue-consume` přepsaný není — dohled nad dlouho běžícím procesem
není workflow a zůstává v bashi.

## Spuštění (až engine vznikne)

```
donut sync      --QUEUE_FILE=… --PROMPTS_DIR=… --WORK_ROOT=… --CURLRC=…
donut card-dev  --SHORT_ID=… --EXPECT_STATUS=ReadyToDev --MODEL=sonnet \
                --SYSTEM_PROMPT=… --TARGET_LIST=Testing --TAG=#developer \
                --WORK_ROOT=… --CURLRC=…
```

`sync` sám sestavuje tahle volání a zařazuje je do fronty přes `jptq`.

## Předpoklady

- `~/.config/donut/trello.curlrc` s přihlašovací hlavičkou pro Trello API;
  cesta se předává jako `--CURLRC`. Engine o credentials neví nic.
- Přepsané `olw-*` příkazy podle rozhraní v návrhovém dokumentu — bez JSON
  obálky, argumenty vstup, stdout výstup.
