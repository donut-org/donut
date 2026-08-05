# Přepis `olw-*` vrstvy — implementační plán

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zrušit v šesti `olw-*` skriptech protokol s JSON obálkou a nahradit ho konvencí „argumenty vstup, stdout výstup", aby je mohl volat donut.

**Architecture:** Každý skript stojí sám — žádná sdílená knihovna. `olw-lib.sh` se maže. Chyba je hláška na stderr a nenulový exit; na stdout jde jen výsledek. Skripty, které donut volá, berou `--klic=hodnota` (tvar předepisují kameny v donutu); ostatní si nechávají poziční argumenty.

**Tech Stack:** bash + `jq`, `gh`, `git`, `curl`. Testy jsou bashová sada v `tests/` se stubováním přes podvržené binárky na `PATH`.

## Kde se pracuje

**V repozitáři `jptoolkit/olw`, na větvi `master`.** V donutu je jen
nagitignorovaný checkout v `docs/workflows/olw/` — tam se pracuje, ale
commity jdou do olw repa.

Absolutní cesta ke kořeni olw repa:
`/home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw`

**Do donutu se v tomhle plánu necommituje vůbec nic.** Tenhle soubor je
jediná stopa, kterou plán v donutu zanechává, a je už commitnutý.

Návrh: `docs/superpowers/specs/2026-08-05-olw-prepis-design.md` (v donutu).

## Global Constraints

- **Bash, tabulátory jako odsazení**, `#!/bin/bash` a `set -euo pipefail` na začátku každého skriptu — přesně jako okolní kód.
- **Žádná sdílená knihovna.** `olw-lib.sh` se maže; žádný skript ho nesmí sourcovat. Funkce `die()` uvnitř jednoho skriptu není sdílená knihovna a je v pořádku.
- **Chybová konvence:** hláška na stderr ve tvaru `Error: <text>`, nenulový exit, na stdout nic. Žádná error obálka, žádné protahování cizí chyby.
- **Hlášky anglicky** — celý olw repozitář je anglicky, na rozdíl od donutu.
- **Tvar argumentů donut-facing příkazů je daný kameny** v `docs/workflows/donut/blocks/` (v donutu). Nesmí se změnit; změna by znamenala zásah do druhého repozitáře.
- **Příkazy, které donut nevolá** (`olw-json` celý, `olw-trello` celý, `olw-task project-detect`), si nechávají poziční argumenty tak, jak je mají dnes.
- **`olw-text` se nedotýkáme.** Obálku nikdy neměl.
- **Pozor na `set -e` u `[ … ] && příkaz`** — když test selže, selže celý příkaz a `set -e` ukončí skript. Piš `if [ … ]; then … fi`. Tvar `[ … ] || příkaz` je bezpečný.
- Po každém tasku musí projít `make check` (`bash -n` nad všemi skripty) i `make test` (celá sada). Spouštěj z kořene olw repa.
- `git add` s konkrétními cestami, nikdy `git add -A` ani `git add .`. V olw repu je adresář `.superpowers/` se starými artefakty — nesahej na něj.

---

### Task 1: `olw-json` — zrušit obálku

Nejmenší skript a čistá ukázka konvence: mizí obálka, argumenty i chování
zůstávají. Zároveň padá `test-pipeline-errors.sh`, který testuje protokol
napříč `olw-task` a `olw-json` — jakmile jeden z nich přestane obálku
vydávat, ztrácí smysl.

**Files:**
- Modify: `olw-json` (celý)
- Test: `tests/test-olw-json.sh` (celý)
- Delete: `tests/test-pipeline-errors.sh`
- Modify: `tests/test-all.sh` (řádek, který ten test spouští)

**Interfaces:**
- Consumes: nic.
- Produces: `olw-json pick <key>...` a `olw-json rename <old> <new>` — čtou JSON ze stdin, vypisují JSON na stdout. Prázdný stdin je chyba.

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-json.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

run_rename() { echo "$1" | "$SCRIPTS_DIR/olw-json" rename "$2" "$3"; }
run_pick()   { echo "$1" | "$SCRIPTS_DIR/olw-json" pick "${@:2}"; }

# --- pick ---

assert_eq "pick single key" \
	'{"abc":1}' \
	"$(run_pick '{"abc":1,"foo":false,"bar":"bar"}' abc | jq -c .)"

assert_eq "pick multiple keys" \
	'{"abc":1,"bar":"bar"}' \
	"$(run_pick '{"abc":1,"foo":false,"bar":"bar"}' abc bar | jq -c .)"

assert_eq "pick nonexistent key" \
	'{}' \
	"$(run_pick '{"abc":1}' missing | jq -c .)"

exit_code=0
"$SCRIPTS_DIR/olw-json" pick </dev/null 2>/dev/null || exit_code=$?
assert_exit_nonzero "pick missing args exits with error" "$exit_code"

# --- rename ---

assert_eq "basic rename" \
	'{"new_key":"value"}' \
	"$(run_rename '{"old_key":"value"}' old_key new_key | jq -c .)"

assert_eq "other keys preserved" \
	'{"other":123,"new_key":"value"}' \
	"$(run_rename '{"other":123,"old_key":"value"}' old_key new_key | jq -c .)"

assert_eq "missing key passes through" \
	'{"other":123}' \
	"$(run_rename '{"other":123}' nonexistent new_key | jq -c .)"

exit_code=0
"$SCRIPTS_DIR/olw-json" rename </dev/null 2>/dev/null || exit_code=$?
assert_exit_nonzero "rename missing args exits with error" "$exit_code"

# --- nová konvence: žádná obálka ---

# klíč "error" na vstupu je obyčejný klíč, ne signál — projde jako každý jiný
out=$(echo '{"data":1,"error":{"message":"up"}}' | "$SCRIPTS_DIR/olw-json" pick data error | jq -c .)
assert_eq "pick: klíč error je obyčejný klíč" '{"data":1,"error":{"message":"up"}}' "$out"

exit_code=0
echo '{"data":1,"error":{"message":"up"}}' | "$SCRIPTS_DIR/olw-json" pick data >/dev/null 2>&1 || exit_code=$?
assert_exit_0 "pick: klíč error nezpůsobí nenulový exit" "$exit_code"

# prázdný stdin je chyba, ne prázdný výstup
exit_code=0
printf '' | "$SCRIPTS_DIR/olw-json" rename a b >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "rename: prázdný stdin je chyba" "$exit_code"

out=$(printf '' | "$SCRIPTS_DIR/olw-json" rename a b 2>&1 >/dev/null || true)
assert_eq "rename: prázdný stdin hlásí na stderr" "true" \
	"$(echo "$out" | grep -q '^Error: ' && echo true || echo false)"

# chyba nesmí nic vypsat na stdout
out=$(printf '' | "$SCRIPTS_DIR/olw-json" rename a b 2>/dev/null || true)
assert_eq "rename: při chybě je stdout prázdný" "" "$out"

summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-json.sh`
Expected: FAIL — `olw-json` dnes na klíč `error` reaguje zkratem, končí nenulově a při prázdném stdin vypisuje obálku na stdout.

- [ ] **Step 3: Přepiš `olw-json`**

Nahraď celý obsah souboru `olw-json`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

# Vrací 1 místo volání die, aby to šlo použít jako INPUT=$(read_json):
# selhání příkazové substituce ukončí skript přes set -e a hláška je už venku.
read_json() {
	local raw
	raw=$(cat)

	if [ -z "${raw//[[:space:]]/}" ]; then
		echo "Error: empty input (expected JSON on stdin)" >&2
		return 1
	fi

	printf '%s' "$raw"
}

cmd="${1:-}"
shift || true

case "$cmd" in
	pick)
		if [ $# -eq 0 ]; then
			echo "Usage: olw-json pick <key>..." >&2
			exit 1
		fi

		INPUT=$(read_json)
		printf '%s' "$INPUT" | jq --args 'with_entries(select(.key | IN($ARGS.positional[])))' -- "$@"
		;;

	rename)
		old_key="${1:-}"
		new_key="${2:-}"

		if [ -z "$old_key" ] || [ -z "$new_key" ]; then
			echo "Usage: olw-json rename <old-key> <new-key>" >&2
			exit 1
		fi

		INPUT=$(read_json)
		printf '%s' "$INPUT" | jq --arg old "$old_key" --arg new "$new_key" \
			'with_entries(if .key == $old then .key = $new else . end)'
		;;

	*)
		echo "Usage: olw-json <command> [<args>]" >&2
		echo "" >&2
		echo "Commands:" >&2
		echo "  pick <key>...               - keep only the specified top-level keys (reads JSON from stdin)" >&2
		echo "  rename <old-key> <new-key>  - rename a top-level key (reads JSON from stdin)" >&2
		exit 1
		;;
esac
```

Pozn.: `read_json` je funkce uvnitř jednoho skriptu, ne knihovna. Stejné řádky
se objeví i v `olw-task` — to je záměr návrhu („žádná sdílená knihovna"), ne
opomenutí.

Pozn. 2: `read_json` vrací `1` a nevolá `die` schválně. V tvaru
`INPUT=$(read_json)` běží tělo funkce v subshellu, takže `exit` by ukončil jen
jeho; návratový kód ale shodí příkazovou substituci a `set -e` shodí skript.
Test „při chybě je stdout prázdný" to hlídá.

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-json.sh`
Expected: PASS, `All N tests passed`.

- [ ] **Step 5: Smaž `test-pipeline-errors.sh` a uprav `test-all.sh`**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git rm tests/test-pipeline-errors.sh
```

V `tests/test-all.sh` smaž řádek `run_suite "$BIN_DIR/test-pipeline-errors.sh"`.

- [ ] **Step 6: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde. `test-olw-lib.sh` dál prochází — testuje knihovnu přímo a ta zatím existuje.

- [ ] **Step 7: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-json tests/test-olw-json.sh tests/test-all.sh
git commit -m "olw-json: drop the JSON envelope protocol"
```

---

### Task 2: `olw-trello` — zrušit obálku, přidat `--text`, tlustá karta

Wrapper nad Trello API. Výchozí výstup je JSON z API; `--text` dává praktický
skalární tvar. `card` nově stahuje kartu včetně komentářů, příloh a checklistů,
aby šla složit s `olw-trello-to-task`.

**Files:**
- Modify: `olw-trello` (celý)
- Test: `tests/test-olw-trello.sh` (celý)

**Interfaces:**
- Consumes: nic.
- Produces:
  - `olw-trello me [--text]` → JSON `{"id":…}`, s `--text` holé ID
  - `olw-trello card <shortId>` → JSON tlusté karty
  - `olw-trello card-move <shortId> <listName>` → nic
  - `olw-trello comments|attachments|checklists <shortId>` → JSON pole
  - `olw-trello comment-add <shortId> [text]` → nic; bez argumentu čte text ze stdin
  - `olw-trello boards [--text]` → JSON, s `--text` jména po řádcích
  - `olw-trello list-cards <board> <list> [--text]` → JSON, s `--text` shortLinky po řádcích

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-trello.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

CFG_DIR=$(mktemp -d)
mkdir -p "$CFG_DIR/olw"
echo '{"apiKey":"k","token":"t"}' > "$CFG_DIR/olw/trello.json"
export XDG_CONFIG_HOME="$CFG_DIR"

# Podvržený curl zapisuje vyžádané URL do souboru a vrací připravenou odpověď.
MOCK_DIR=$(mktemp -d)
URL_LOG="$MOCK_DIR/urls"
cat > "$MOCK_DIR/curl" << 'CURLEOF'
#!/bin/bash
for a in "$@"; do
	case "$a" in
		https://*) echo "$a" >> "$URL_LOG" ;;
	esac
done
if [ -f "$MOCK_RESPONSE" ]; then cat "$MOCK_RESPONSE"; fi
CURLEOF
chmod +x "$MOCK_DIR/curl"
export URL_LOG
export MOCK_RESPONSE="$MOCK_DIR/response"
export PATH="$MOCK_DIR:$PATH"

reset_log() { : > "$URL_LOG"; }
last_url()  { tail -n 1 "$URL_LOG"; }

# --- me ---

echo '{"id":"me123"}' > "$MOCK_RESPONSE"
reset_log
assert_eq "me: výchozí výstup je JSON z API" '{"id":"me123"}' \
	"$("$SCRIPTS_DIR/olw-trello" me | jq -c .)"
assert_eq "me: --text dá holé ID" "me123" \
	"$("$SCRIPTS_DIR/olw-trello" me --text)"

# --- card: tlustá karta ---

echo '{"shortLink":"abc","name":"T"}' > "$MOCK_RESPONSE"
reset_log
"$SCRIPTS_DIR/olw-trello" card abc123 > /dev/null
URL=$(last_url)
assert_eq "card: žádá actions=commentCard" "true" \
	"$(echo "$URL" | grep -q 'actions=commentCard' && echo true || echo false)"
assert_eq "card: žádá attachments=true" "true" \
	"$(echo "$URL" | grep -q 'attachments=true' && echo true || echo false)"
assert_eq "card: žádá checklists=all" "true" \
	"$(echo "$URL" | grep -q 'checklists=all' && echo true || echo false)"
assert_eq "card: vypíše odpověď beze změny" '{"shortLink":"abc","name":"T"}' \
	"$("$SCRIPTS_DIR/olw-trello" card abc123 | jq -c .)"

# --- boards a list-cards ---

echo '[{"name":"Board A"},{"name":"Board B"}]' > "$MOCK_RESPONSE"
reset_log
assert_eq "boards: výchozí výstup je JSON" "2" \
	"$("$SCRIPTS_DIR/olw-trello" boards | jq 'length')"
assert_eq "boards: --text dá jména po řádcích" "Board A
Board B" \
	"$("$SCRIPTS_DIR/olw-trello" boards --text)"

# --- comment-add: text z argumentu i ze stdin ---

echo '{"id":"cardid"}' > "$MOCK_RESPONSE"
reset_log
"$SCRIPTS_DIR/olw-trello" comment-add abc123 "z argumentu" > /dev/null
assert_eq "comment-add: text z argumentu neselže" "true" \
	"$([ -s "$URL_LOG" ] && echo true || echo false)"

reset_log
echo "ze stdin" | "$SCRIPTS_DIR/olw-trello" comment-add abc123 > /dev/null
assert_eq "comment-add: text ze stdin neselže" "true" \
	"$([ -s "$URL_LOG" ] && echo true || echo false)"

exit_code=0
printf '' | "$SCRIPTS_DIR/olw-trello" comment-add abc123 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "comment-add: prázdný text je chyba" "$exit_code"

# --- klíč error na stdin už nic neznamená ---

echo '{"id":"cardid"}' > "$MOCK_RESPONSE"
exit_code=0
echo '{"error":{"message":"up"}}' | "$SCRIPTS_DIR/olw-trello" comment-add abc123 >/dev/null 2>&1 || exit_code=$?
assert_exit_0 "comment-add: klíč error na stdin je obyčejný text" "$exit_code"

# --- chybějící config ---

CFG_EMPTY=$(mktemp -d)
exit_code=0
XDG_CONFIG_HOME="$CFG_EMPTY" "$SCRIPTS_DIR/olw-trello" me >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "chybějící config: nenulový exit" "$exit_code"

out=$(XDG_CONFIG_HOME="$CFG_EMPTY" "$SCRIPTS_DIR/olw-trello" me 2>/dev/null || true)
assert_eq "chybějící config: stdout je prázdný" "" "$out"

err=$(XDG_CONFIG_HOME="$CFG_EMPTY" "$SCRIPTS_DIR/olw-trello" me 2>&1 >/dev/null || true)
assert_eq "chybějící config: hláška na stderr" "true" \
	"$(echo "$err" | grep -q '^Error: config not found' && echo true || echo false)"
rm -rf "$CFG_EMPTY"

rm -rf "$CFG_DIR" "$MOCK_DIR"
summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-trello.sh`
Expected: FAIL — `me` dnes vypisuje payload s klíčem `trello_me`, `--text` nezná, `card` stahuje jen `list=true` a chybějící config vypisuje obálku na stdout.

- [ ] **Step 3: Přepiš `olw-trello`**

Nahraď celý obsah souboru `olw-trello`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

# Wrapper nad Trello API: výchozí výstup je JSON z API, --text dává praktický
# skalární tvar tam, kde dává smysl.

CONFIG_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/olw"
CONFIG_FILE="$CONFIG_DIR/trello.json"

if [ ! -f "$CONFIG_FILE" ]; then
	die "config not found: $CONFIG_FILE"
fi

API_KEY=$(jq -r '.apiKey // empty' "$CONFIG_FILE")
TOKEN=$(jq -r '.token // empty' "$CONFIG_FILE")
API_BASE="https://api.trello.com/1"

if [ -z "$API_KEY" ] || [ -z "$TOKEN" ]; then
	die "apiKey or token missing in $CONFIG_FILE"
fi

trello_get() {
	local path="$1"
	local params="${2:-}"
	local query="${params:+${params}&}key=${API_KEY}&token=${TOKEN}"
	curl -sSf "${API_BASE}${path}?${query}"
}

has_text_flag() {
	local a
	for a in "$@"; do
		if [ "$a" = "--text" ]; then
			return 0
		fi
	done
	return 1
}

cmd="${1:-}"
shift || true

case "$cmd" in
	me)
		RESULT=$(trello_get "/members/me" "fields=id")

		if has_text_flag "$@"; then
			echo "$RESULT" | jq -r '.id'
		else
			echo "$RESULT"
		fi
		;;

	card)
		SHORT_ID="${1:?Usage: olw-trello card <shortId>}"
		trello_get "/cards/${SHORT_ID}" "list=true&actions=commentCard&attachments=true&checklists=all"
		;;

	card-move)
		SHORT_ID="${1:?Usage: olw-trello card-move <shortId> <listName>}"
		LIST_NAME="${2:?Usage: olw-trello card-move <shortId> <listName>}"

		CARD=$(trello_get "/cards/${SHORT_ID}" "fields=id,idBoard") || \
			die "card '${SHORT_ID}' not found"
		CARD_ID=$(echo "$CARD" | jq -r '.id')
		ID_BOARD=$(echo "$CARD" | jq -r '.idBoard')

		LISTS=$(trello_get "/boards/${ID_BOARD}/lists" "fields=id,name")
		TARGET_ID=$(echo "$LISTS" | jq -r --arg name "$LIST_NAME" '.[] | select(.name == $name) | .id')

		if [ -z "$TARGET_ID" ]; then
			die "list '${LIST_NAME}' not found on board"
		fi

		curl -sSf -X PUT \
			"${API_BASE}/cards/${CARD_ID}?key=${API_KEY}&token=${TOKEN}" \
			-H "Content-Type: application/json" \
			-d "$(jq -n --arg idList "$TARGET_ID" '{idList: $idList}')" > /dev/null
		;;

	comments)
		SHORT_ID="${1:?Usage: olw-trello comments <shortId>}"
		trello_get "/cards/${SHORT_ID}/actions" "filter=commentCard"
		;;

	attachments)
		SHORT_ID="${1:?Usage: olw-trello attachments <shortId>}"
		trello_get "/cards/${SHORT_ID}/attachments"
		;;

	checklists)
		SHORT_ID="${1:?Usage: olw-trello checklists <shortId>}"
		trello_get "/cards/${SHORT_ID}/checklists"
		;;

	comment-add)
		SHORT_ID="${1:?Usage: olw-trello comment-add <shortId> [text]}"
		shift || true

		if [ $# -ge 1 ]; then
			COMMENT_TEXT="$1"
		else
			COMMENT_TEXT=$(cat)
		fi

		if [ -z "${COMMENT_TEXT//[[:space:]]/}" ]; then
			die "empty comment text (pass as argument or on stdin)"
		fi

		CARD_ID=$(trello_get "/cards/${SHORT_ID}" "fields=id" | jq -r '.id') || \
			die "card '${SHORT_ID}' not found"

		curl -sSf -X POST \
			"${API_BASE}/cards/${CARD_ID}/actions/comments?key=${API_KEY}&token=${TOKEN}" \
			-H "Content-Type: application/json" \
			-d "$(jq -n --arg text "$COMMENT_TEXT" '{text: $text}')" > /dev/null
		;;

	boards)
		RESULT=$(trello_get "/members/me/boards" "fields=name")

		if has_text_flag "$@"; then
			echo "$RESULT" | jq -r '.[].name'
		else
			echo "$RESULT"
		fi
		;;

	list-cards)
		BOARD_NAME="${1:?Usage: olw-trello list-cards <boardName> <listName>}"
		LIST_NAME="${2:?Usage: olw-trello list-cards <boardName> <listName>}"

		BOARD_ID=$(trello_get "/members/me/boards" "fields=id,name" | \
			jq -r --arg name "$BOARD_NAME" '.[] | select(.name == $name) | .id')

		if [ -z "$BOARD_ID" ]; then
			die "board '${BOARD_NAME}' not found"
		fi

		LIST_ID=$(trello_get "/boards/${BOARD_ID}/lists" "fields=id,name" | \
			jq -r --arg name "$LIST_NAME" '.[] | select(.name == $name) | .id')

		if [ -z "$LIST_ID" ]; then
			die "list '${LIST_NAME}' not found on board '${BOARD_NAME}'"
		fi

		RESULT=$(trello_get "/lists/${LIST_ID}/cards" "fields=shortLink")

		if has_text_flag "$@"; then
			echo "$RESULT" | jq -r '.[].shortLink'
		else
			echo "$RESULT"
		fi
		;;

	*)
		echo "Usage: olw-trello <command> [<args>]" >&2
		echo "" >&2
		echo "Output is the JSON returned by the Trello API. --text prints a plain scalar form." >&2
		echo "" >&2
		echo "Commands:" >&2
		echo "  me [--text]                             - my member record; --text prints just the id" >&2
		echo "  card <shortId>                          - card incl. comments, attachments and checklists" >&2
		echo "  card-move <shortId> <listName>          - move card to list" >&2
		echo "  comments <shortId>                      - comment actions on the card" >&2
		echo "  attachments <shortId>                   - attachments of the card" >&2
		echo "  checklists <shortId>                    - checklists of the card" >&2
		echo "  comment-add <shortId> [text]            - add a comment; reads text from stdin if not given" >&2
		echo "  boards [--text]                         - my boards; --text prints names, one per line" >&2
		echo "  list-cards <board> <list> [--text]      - cards in a list; --text prints shortLinks" >&2
		exit 1
		;;
esac
```

Pozor: `has_text_flag` se schválně volá až uvnitř větví a **neshiftuje** —
`comment-add` bere text pozičně a `--text` by mu jinak zmizel z argumentů.

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-trello.sh`
Expected: PASS.

- [ ] **Step 5: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde.

- [ ] **Step 6: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-trello tests/test-olw-trello.sh
git commit -m "olw-trello: drop the envelope, add --text, fetch the full card"
```

---

### Task 3: `olw-trello-to-task` — tlustá karta dovnitř, holý task ven

Čte jednu kartu z `/1/cards/{id}` včetně `actions`, `attachments`
a `checklists` a vypíše **holý task objekt** bez obalu `olw_task`. Je to
čtyřicet řádků jq, kde tiché selhání nikdo nepozná — proto sem patří
nejpodrobnější testy z celého plánu.

**Files:**
- Modify: `olw-trello-to-task` (celý)
- Test: `tests/test-olw-trello-to-task.sh` (celý)

**Interfaces:**
- Consumes: `olw-trello card <shortId>` z Tasku 2 vypisuje přesně ten tvar karty, který tenhle příkaz čte.
- Produces: `olw-trello-to-task [--my-id=<id>]` — stdin tlustá karta, stdout holý task JSON s poli `id`, `title`, `status`, `project`, `repo`, `branch`, `description`, `comments`, `attachments`, `checklists`.

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-trello-to-task.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# Tlustá karta v tom tvaru, v jakém ji vrací
# /1/cards/{id}?list=true&actions=commentCard&attachments=true&checklists=all
CARD=$(jq -n '{
	shortLink: "AXew3CkL",
	name: "Fix login bug",
	desc: "The login form breaks on mobile",
	list: {name: "ReadyToDev"},
	actions: [
		{
			date: "2024-01-11T10:00:00.000Z",
			memberCreator: {id: "me123", fullName: "Bot"},
			data: {text: "Fixed in commit abc123."}
		},
		{
			date: "2024-01-10T10:00:00.000Z",
			memberCreator: {id: "user1", fullName: "Jane"},
			data: {text: "Reproduced on Safari."}
		}
	],
	attachments: [
		{name: "design.pdf", url: "https://trello.com/a/1/design.pdf", extra: "ignored"},
		{name: "screenshot.png", url: "https://trello.com/a/2/screenshot.png"}
	],
	checklists: [
		{
			name: "Definition of Done",
			checkItems: [
				{name: "Tests written", state: "complete"},
				{name: "Reviewed", state: "incomplete"}
			]
		}
	]
}')

output=$(echo "$CARD" | "$SCRIPTS_DIR/olw-trello-to-task" --my-id=me123)

# --- tvar výstupu: holý task, žádný obal ---

assert_eq "výstup nemá obal olw_task" "null" "$(echo "$output" | jq '.olw_task')"
assert_eq "id from shortLink" "AXew3CkL" "$(echo "$output" | jq -r '.id')"
assert_eq "title from name" "Fix login bug" "$(echo "$output" | jq -r '.title')"
assert_eq "project is default" "default" "$(echo "$output" | jq -r '.project')"
assert_eq "repo is null" "null" "$(echo "$output" | jq -r '.repo')"
assert_eq "branch is task-shortLink" "task-AXew3CkL" "$(echo "$output" | jq -r '.branch')"
assert_eq "description from desc" "The login form breaks on mobile" "$(echo "$output" | jq -r '.description')"
assert_eq "status from list name" "ReadyToDev" "$(echo "$output" | jq -r '.status')"

# --- komentáře: z .actions, seřazené podle data ---

assert_eq "comments count" "2" "$(echo "$output" | jq '.comments | length')"
assert_eq "comments seřazené nejstarší první" "Reproduced on Safari." \
	"$(echo "$output" | jq -r '.comments[0].text')"
assert_eq "comment author preserved" "Jane" "$(echo "$output" | jq -r '.comments[0].author')"
assert_eq "comment date trimmed" "2024-01-10" "$(echo "$output" | jq -r '.comments[0].date')"

# --- role podle --my-id ---

assert_eq "cizí komentář má role user" "user" "$(echo "$output" | jq -r '.comments[0].role')"
assert_eq "můj komentář má role assistant" "assistant" "$(echo "$output" | jq -r '.comments[1].role')"

no_id=$(echo "$CARD" | "$SCRIPTS_DIR/olw-trello-to-task")
assert_eq "bez --my-id jsou všechny komentáře user" "user" \
	"$(echo "$no_id" | jq -r '.comments[1].role')"

# --- přílohy a checklisty ---

assert_eq "attachments count" "2" "$(echo "$output" | jq '.attachments | length')"
assert_eq "attachment name" "design.pdf" "$(echo "$output" | jq -r '.attachments[0].name')"
assert_eq "attachment url" "https://trello.com/a/1/design.pdf" "$(echo "$output" | jq -r '.attachments[0].url')"
assert_eq "attachment extra fields stripped" "null" "$(echo "$output" | jq '.attachments[0].extra')"
assert_eq "checklists count" "1" "$(echo "$output" | jq '.checklists | length')"
assert_eq "checklist name" "Definition of Done" "$(echo "$output" | jq -r '.checklists[0].name')"
assert_eq "checklist items count" "2" "$(echo "$output" | jq '.checklists[0].items | length')"
assert_eq "checklist item checked=true" "true" "$(echo "$output" | jq -r '.checklists[0].items[0].checked')"
assert_eq "checklist item checked=false" "false" "$(echo "$output" | jq -r '.checklists[0].items[1].checked')"

# --- chybějící sekce dávají prázdná pole, ne chybu ---

MINIMAL=$(jq -n '{shortLink: "ABC", name: "Task", desc: ""}')
output=$(echo "$MINIMAL" | "$SCRIPTS_DIR/olw-trello-to-task")
assert_eq "bez actions → prázdné pole" "[]" "$(echo "$output" | jq -c '.comments')"
assert_eq "bez attachments → prázdné pole" "[]" "$(echo "$output" | jq -c '.attachments')"
assert_eq "bez checklists → prázdné pole" "[]" "$(echo "$output" | jq -c '.checklists')"
assert_eq "bez list → status null" "null" "$(echo "$output" | jq -r '.status')"

# --- chybové cesty ---

exit_code=0
printf '' | "$SCRIPTS_DIR/olw-trello-to-task" >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "prázdný stdin je chyba" "$exit_code"

exit_code=0
echo "$CARD" | "$SCRIPTS_DIR/olw-trello-to-task" --bogus=1 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "neznámý argument je chyba" "$exit_code"

out=$(printf '' | "$SCRIPTS_DIR/olw-trello-to-task" 2>/dev/null || true)
assert_eq "při chybě je stdout prázdný" "" "$out"

summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-trello-to-task.sh`
Expected: FAIL — dnešní skript čte `.trello_card` a spol. a vypisuje payload s obalem `olw_task`.

- [ ] **Step 3: Přepiš `olw-trello-to-task`**

Nahraď celý obsah souboru `olw-trello-to-task`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

MY_ID=""

while [ $# -gt 0 ]; do
	case "$1" in
		--my-id=*) MY_ID="${1#*=}" ;;
		*) die "unknown argument: $1" ;;
	esac
	shift
done

CARD=$(cat)

if [ -z "${CARD//[[:space:]]/}" ]; then
	die "empty input (expected card JSON on stdin)"
fi

# Vstup je jedna karta z /1/cards/{id} včetně actions, attachments
# a checklists — komentáře jsou v .actions, ne ve zvláštním poli.
printf '%s' "$CARD" | jq --arg myId "$MY_ID" '{
	id: .shortLink,
	title: .name,
	status: (.list.name // null),
	project: "default",
	repo: null,
	branch: ("task-" + .shortLink),
	description: (.desc // ""),
	comments: [
		((.actions // []) | sort_by(.date))[] | {
			author: .memberCreator.fullName,
			role: (if $myId != "" and .memberCreator.id == $myId then "assistant" else "user" end),
			date: .date[:10],
			text: .data.text
		}
	],
	attachments: [
		(.attachments // [])[] | {name: .name, url: .url}
	],
	checklists: [
		(.checklists // [])[] | {
			name: .name,
			items: [.checkItems[] | {name: .name, checked: (.state == "complete")}]
		}
	]
}'
```

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-trello-to-task.sh`
Expected: PASS.

- [ ] **Step 5: Ověř složení s `olw-trello card`**

Tvar karty, který `olw-trello card` vrací, a tvar, který tenhle příkaz čte,
drží pohromadě jen dohodou. Ověř ručně, že klíče sedí:

Run:
```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
grep -n 'list=true&actions=commentCard&attachments=true&checklists=all' olw-trello
grep -n '\.list\.name\|\.actions\|\.attachments\|\.checklists' olw-trello-to-task
```
Expected: `olw-trello` žádá `list`, `actions`, `attachments` a `checklists`;
`olw-trello-to-task` čte přesně ty čtyři. Když se neshodují, oprav — složení
těch dvou příkazů je důvod, proč `card` stahuje tlustou kartu.

- [ ] **Step 6: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde.

- [ ] **Step 7: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-trello-to-task tests/test-olw-trello-to-task.sh
git commit -m "olw-trello-to-task: read one full card, emit a bare task"
```

---

### Task 4: `olw-task` — `format-prompt`, `repo-find`, `project-detect`

Tři podpříkazy zůstávají, `status-assert` mizí — bez obálky by z něj zbylo
porovnání dvou řetězců a donut ho nahradil `if` + kámen nad `/usr/bin/false`.

**Files:**
- Modify: `olw-task` (celý)
- Test: `tests/test-olw-task.sh` (celý)

**Interfaces:**
- Consumes: task JSON v tom tvaru, který produkuje `olw-trello-to-task` z Tasku 3 — holý objekt, žádný obal.
- Produces:
  - `olw-task format-prompt` — stdin task JSON, stdout text promptu
  - `olw-task repo-find --project=<name>` — bez stdin, stdout `owner/name` nebo nic
  - `olw-task project-detect` — stdin task JSON, stdout task JSON

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-task.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# --- repo-find ---

# bez --project= vypíše nic a skončí nulou; na tom stojí zpracování karet
# bez repozitáře, protože donut skupinu s prázdnou hodnotou zahodí celou
out=$("$SCRIPTS_DIR/olw-task" repo-find 2>/dev/null)
assert_eq "repo-find: bez --project je výstup prázdný" "" "$out"

exit_code=0
"$SCRIPTS_DIR/olw-task" repo-find >/dev/null 2>&1 || exit_code=$?
assert_exit_0 "repo-find: bez --project končí nulou" "$exit_code"

# projekt se lomítkem je rovnou owner/name, jen se zmenší
assert_eq "repo-find: projekt s lomítkem se zmenší" "webilion/my-app" \
	"$("$SCRIPTS_DIR/olw-task" repo-find --project=Webilion/My-App)"

mock_gh() {
	MOCK_DIR=$(mktemp -d)
	printf '#!/bin/bash\necho %s\n' "'$1'" > "$MOCK_DIR/gh"
	chmod +x "$MOCK_DIR/gh"
}

mock_gh '[{"nameWithOwner":"webilion/my-project","isFork":false},{"nameWithOwner":"webilion/other","isFork":false}]'
assert_eq "repo-find: jeden nález" "webilion/my-project" \
	"$(PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project)"
rm -rf "$MOCK_DIR"

mock_gh '[{"nameWithOwner":"webilion/My-Project","isFork":false}]'
assert_eq "repo-find: shoda nezávisle na velikosti písmen" "webilion/My-Project" \
	"$(PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project)"
rm -rf "$MOCK_DIR"

mock_gh '[{"nameWithOwner":"webilion/my-project","isFork":true}]'
assert_eq "repo-find: forky se nepočítají" "" \
	"$(PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project 2>/dev/null)"
rm -rf "$MOCK_DIR"

# nula nálezů: prázdný stdout a exit 0, ne chyba
mock_gh '[{"nameWithOwner":"webilion/other","isFork":false}]'
assert_eq "repo-find: nula nálezů dá prázdný stdout" "" \
	"$(PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project 2>/dev/null)"

exit_code=0
PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project >/dev/null 2>&1 || exit_code=$?
assert_exit_0 "repo-find: nula nálezů končí nulou" "$exit_code"
rm -rf "$MOCK_DIR"

# víc nálezů je chyba
mock_gh '[{"nameWithOwner":"webilion/my-project","isFork":false},{"nameWithOwner":"other-org/my-project","isFork":false}]'
exit_code=0
PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-task" repo-find --project=my-project >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "repo-find: víc nálezů je chyba" "$exit_code"
rm -rf "$MOCK_DIR"

exit_code=0
"$SCRIPTS_DIR/olw-task" repo-find --bogus=1 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "repo-find: neznámý argument je chyba" "$exit_code"

# --- format-prompt ---

run_fp() { echo "$1" | "$SCRIPTS_DIR/olw-task" format-prompt; }

MINIMAL='{"id":"abc","title":"Fix login","description":"","comments":[],"project":"p","repo":null,"branch":"b"}'
out=$(run_fp "$MINIMAL")

assert_eq "format-prompt: výstup je text, ne JSON" "false" \
	"$(echo "$out" | jq -e . >/dev/null 2>&1 && echo true || echo false)"
assert_eq "format-prompt: title v promptu" "true" \
	"$(echo "$out" | grep -q '<title>Fix login</title>' && echo true || echo false)"
assert_eq "format-prompt: prázdný popis nedá blok description" "false" \
	"$(echo "$out" | grep -q '<description>' && echo true || echo false)"
assert_eq "format-prompt: žádné komentáře nedají blok comments" "false" \
	"$(echo "$out" | grep -q '<comments>' && echo true || echo false)"

FULL='{"id":"abc","title":"T","description":"D","project":"p","repo":null,"branch":"b",
	"comments":[{"author":"Jane","role":"user","date":"2024-01-10","text":"Hello"}],
	"checklists":[{"name":"DoD","items":[{"name":"Tests","checked":true},{"name":"Review","checked":false}]}],
	"attachments":[{"name":"design.pdf","url":"https://example.com/d.pdf"}]}'
out=$(run_fp "$FULL")

assert_eq "format-prompt: popis v promptu" "true" \
	"$(echo "$out" | grep -q '<description>' && echo true || echo false)"
assert_eq "format-prompt: komentář i s rolí" "true" \
	"$(echo "$out" | grep -q 'role="user"' && echo true || echo false)"
assert_eq "format-prompt: odškrtnutá položka" "true" \
	"$(echo "$out" | grep -q -- '- \[x\] Tests' && echo true || echo false)"
assert_eq "format-prompt: neodškrtnutá položka" "true" \
	"$(echo "$out" | grep -q -- '- \[ \] Review' && echo true || echo false)"
assert_eq "format-prompt: příloha" "true" \
	"$(echo "$out" | grep -q 'design.pdf' && echo true || echo false)"

exit_code=0
printf '' | "$SCRIPTS_DIR/olw-task" format-prompt >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "format-prompt: prázdný stdin je chyba" "$exit_code"

# --- project-detect ---

run_pd() { echo "$1" | "$SCRIPTS_DIR/olw-task" project-detect; }

out=$(run_pd '{"title":"Webilion - Fix login","project":"default","id":"abc"}')
assert_eq "project-detect: projekt z prefixu" "Webilion" "$(echo "$out" | jq -r '.project')"
assert_eq "project-detect: title bez prefixu" "Fix login" "$(echo "$out" | jq -r '.title')"
assert_eq "project-detect: ostatní pole zachována" "abc" "$(echo "$out" | jq -r '.id')"

out=$(run_pd '{"title":"Fix login","project":"default"}')
assert_eq "project-detect: bez prefixu se projekt nemění" "default" "$(echo "$out" | jq -r '.project')"
assert_eq "project-detect: bez prefixu se title nemění" "Fix login" "$(echo "$out" | jq -r '.title')"

exit_code=0
printf '' | "$SCRIPTS_DIR/olw-task" project-detect >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "project-detect: prázdný stdin je chyba" "$exit_code"

# --- status-assert zanikl ---

exit_code=0
echo '{"status":"x"}' | "$SCRIPTS_DIR/olw-task" status-assert x >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "status-assert už neexistuje" "$exit_code"

summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-task.sh`
Expected: FAIL — `repo-find` dnes čte payload ze stdin a `--project=` nezná, `format-prompt` vypisuje JSON s klíčem `olw_agent`.

- [ ] **Step 3: Přepiš `olw-task`**

Nahraď celý obsah souboru `olw-task`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

# Vrací 1 místo volání die — viz poznámka u olw-json: v tvaru
# INPUT=$(read_json) běží tělo v subshellu, takže exit by ukončil jen jeho.
read_json() {
	local raw
	raw=$(cat)

	if [ -z "${raw//[[:space:]]/}" ]; then
		echo "Error: empty input (expected task JSON on stdin)" >&2
		return 1
	fi

	printf '%s' "$raw"
}

cmd="${1:-}"
shift || true

case "$cmd" in
	project-detect)
		INPUT=$(read_json)
		printf '%s' "$INPUT" | jq '
			if (.title | test("^[^ ]+ - "))
			then .project = (.title | sub(" - .*"; ""))
				| .title = (.title | sub("^[^ ]* - "; ""))
			else . end'
		;;

	repo-find)
		PROJECT=""

		while [ $# -gt 0 ]; do
			case "$1" in
				--project=*) PROJECT="${1#*=}" ;;
				*) die "unknown argument: $1" ;;
			esac
			shift
		done

		# Prázdný projekt není chyba: donut zahodí celou skupinu argumentů,
		# jejíž proměnná je prázdná, takže --project= sem vůbec nedorazí.
		if [ -z "$PROJECT" ]; then
			exit 0
		fi

		if [[ "$PROJECT" == */* ]]; then
			echo "${PROJECT,,}"
			exit 0
		fi

		ALL_REPOS=$(
			gh repo list --json nameWithOwner,isFork --limit 1000
			for org in $(gh api /user/orgs --jq '.[].login'); do
				gh repo list "$org" --json nameWithOwner,isFork --limit 1000
			done
		)

		MATCHES_JSON=$(echo "$ALL_REPOS" | jq -rs --arg name "${PROJECT,,}" '
			(add // []) | map(
				select(.isFork == false) |
				(.nameWithOwner | split("/")[1] | ascii_downcase) as $rname |
				select($rname == $name) |
				.nameWithOwner
			) | unique')

		MATCH_COUNT=$(echo "$MATCHES_JSON" | jq 'length')

		if [ "$MATCH_COUNT" -eq 0 ]; then
			# Nenalezeno je legitimní výsledek, ne chyba — karta se pak
			# zpracuje bez repozitáře.
			echo "Warning: no repository found matching '$PROJECT'" >&2
		elif [ "$MATCH_COUNT" -eq 1 ]; then
			echo "$MATCHES_JSON" | jq -r '.[0]'
		else
			echo "$MATCHES_JSON" | jq -r '.[]' >&2
			die "multiple repositories match '$PROJECT'"
		fi
		;;

	format-prompt)
		INPUT=$(read_json)
		printf '%s' "$INPUT" | jq -r '
			"<ticket>\n\n<title>" + .title + "</title>" +
			(if (.description // "") != "" then "\n\n<description>\n" + .description + "\n</description>" else "" end) +
			(if ((.comments // []) | length) > 0 then
				"\n\n<comments>\n" +
				([ .comments[] |
					"<comment role=\"" + .role + "\" author=\"" + .author + "\" date=\"" + .date + "\">\n" + .text + "\n</comment>"
				] | join("\n")) +
				"\n</comments>"
			else "" end) +
			(if ((.checklists // []) | length) > 0 then
				"\n\n<checklists>\n" +
				([ .checklists[] |
					"<checklist name=\"" + .name + "\">\n" +
					([ .items[] | (if .checked then "- [x] " else "- [ ] " end) + .name ] | join("\n")) +
					"\n</checklist>"
				] | join("\n")) +
				"\n</checklists>"
			else "" end) +
			(if ((.attachments // []) | length) > 0 then
				"\n\n<attachments>\n" +
				([ .attachments[] |
					"<attachment name=\"" + .name + "\">" + .url + "</attachment>"
				] | join("\n")) +
				"\n</attachments>"
			else "" end) +
			"\n\n</ticket>"'
		;;

	*)
		echo "Usage: olw-task <command> [<args>]" >&2
		echo "" >&2
		echo "Commands:" >&2
		echo "  project-detect            - split \"Project - Title\" into project and title (task JSON on stdin)" >&2
		echo "  repo-find --project=<name> - print owner/name of the matching repo, or nothing" >&2
		echo "  format-prompt             - build the agent prompt (task JSON on stdin, text on stdout)" >&2
		exit 1
		;;
esac
```

Pozn.: `(.description // "") != ""` proti dnešnímu `.description != ""` —
`null` by v původním tvaru prošel podmínkou a `+ null` by v jq spadlo.

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-task.sh`
Expected: PASS.

- [ ] **Step 5: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde.

- [ ] **Step 6: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-task tests/test-olw-task.sh
git commit -m "olw-task: drop the envelope, drop status-assert"
```

---

### Task 5: `olw-workspace` — `init`, `finish`, `pr`

Orchestrace gitu a `gh`. Nejcitlivější místo je `pr`: URL se vypisuje **jen
když příkaz PR sám založil**. Na tom stojí `if prUrl not_empty` v `card-dev` —
u existujícího PR se do komentáře řádek s URL nepřidá.

**Files:**
- Modify: `olw-workspace` (celý)
- Test: `tests/test-olw-workspace.sh` (celý)

**Interfaces:**
- Consumes: `olw-text webalize <text>` (sourozenec, nemění se).
- Produces:
  - `olw-workspace init --root= --project= [--repo=] [--branch=]` → absolutní cesta k workDir na stdout
  - `olw-workspace finish --work-dir= [--repo=] --message=` → nic na stdout
  - `olw-workspace pr --work-dir= [--repo=] --title=` + tělo PR na stdin → URL na stdout, jen když PR sám založil

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-workspace.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# --- init ---

exit_code=0
"$SCRIPTS_DIR/olw-workspace" init --root=/tmp >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "init: chybějící --project je chyba" "$exit_code"

exit_code=0
"$SCRIPTS_DIR/olw-workspace" init --project=p >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "init: chybějící --root je chyba" "$exit_code"

exit_code=0
"$SCRIPTS_DIR/olw-workspace" init --root=/tmp --project=p --bogus=1 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "init: neznámý argument je chyba" "$exit_code"

# bez --repo jen založí adresář a vypíše cestu
ROOT=$(mktemp -d)
out=$("$SCRIPTS_DIR/olw-workspace" init --root="$ROOT" --project=my-project)
assert_eq "init: bez repo vypíše cestu" "$ROOT/my-project" "$out"
assert_eq "init: bez repo založí adresář" "true" "$([ -d "$ROOT/my-project" ] && echo true || echo false)"
rm -rf "$ROOT"

# jméno adresáře se webalizuje
ROOT=$(mktemp -d)
out=$("$SCRIPTS_DIR/olw-workspace" init --root="$ROOT" --project="Můj-Projekt")
assert_eq "init: jméno projektu se webalizuje" "$ROOT/muj-projekt" "$out"
rm -rf "$ROOT"

# s --repo: fork, clone, checkout větve
ROOT=$(mktemp -d)
MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/gh" << 'GHEOF'
#!/bin/bash
case "${1:-}" in
	api) echo "testuser" ;;
	repo)
		case "${2:-}" in
			fork) exit 0 ;;
			sync) exit 0 ;;
			clone)
				/usr/bin/git init -q "$4"
				/usr/bin/git -C "$4" config user.email t@t
				/usr/bin/git -C "$4" config user.name t
				/usr/bin/git -C "$4" commit -q --allow-empty -m init
				# Skript odbočuje větev z origin/<default>, takže ta reference
				# musí existovat i v podvrženém klonu.
				/usr/bin/git -C "$4" update-ref refs/remotes/origin/main HEAD
				/usr/bin/git -C "$4" symbolic-ref refs/remotes/origin/HEAD refs/remotes/origin/main
				;;
		esac
		;;
esac
GHEOF
chmod +x "$MOCK_DIR/gh"

out=$(PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-workspace" \
	init --root="$ROOT" --project=my-project --repo=org/my-project --branch=task-abc 2>/dev/null)
assert_eq "init: s repo je workDir pod repos/" "$ROOT/repos/my-project" "$out"
assert_eq "init: větev je odbočená" "task-abc" "$(git -C "$ROOT/repos/my-project" branch --show-current)"
rm -rf "$ROOT" "$MOCK_DIR"

# --- finish ---

# bez --repo nedělá nic a nic nevypíše
out=$("$SCRIPTS_DIR/olw-workspace" finish --work-dir=/nonexistent --message=m)
assert_eq "finish: bez repo nic nevypíše" "" "$out"

# s --repo: commitne a pushne
WORK=$(mktemp -d)
git -C "$WORK" init -q
# Konfigurace musí být v repozitáři, ne jen u prvního commitu — commit dělá
# skript a ten -c nepředává.
git -C "$WORK" config user.email t@t
git -C "$WORK" config user.name t
git -C "$WORK" commit -q --allow-empty -m base
git -C "$WORK" checkout -q -b feature
echo "změna" > "$WORK/soubor.txt"

MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/git" << 'GITEOF'
#!/bin/bash
# push jen zaznamenáme, zbytek pustíme na skutečný git
for a in "$@"; do
	if [ "$a" = "push" ]; then
		echo "pushed" >> "$PUSH_LOG"
		exit 0
	fi
done
exec /usr/bin/git "$@"
GITEOF
chmod +x "$MOCK_DIR/git"
export PUSH_LOG="$MOCK_DIR/pushes"
: > "$PUSH_LOG"

PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-workspace" \
	finish --work-dir="$WORK" --repo=org/r --message="feature: hotovo" 2>/dev/null

assert_eq "finish: změna je commitnutá" "feature: hotovo" \
	"$(git -C "$WORK" log -1 --pretty=%s)"
assert_eq "finish: pracovní strom je čistý" "" "$(git -C "$WORK" status --porcelain)"
rm -rf "$WORK" "$MOCK_DIR"

exit_code=0
"$SCRIPTS_DIR/olw-workspace" finish --work-dir=/tmp --repo=org/r --bogus=1 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "finish: neznámý argument je chyba" "$exit_code"

# --- pr ---

# bez --repo nic nevypíše a stdin přesto přečte
out=$(echo "tělo" | "$SCRIPTS_DIR/olw-workspace" pr --work-dir=/nonexistent --title=T)
assert_eq "pr: bez repo nic nevypíše" "" "$out"

setup_pr_repo() {
	WORK=$(mktemp -d)
	git -C "$WORK" init -q
	git -C "$WORK" config user.email t@t
	git -C "$WORK" config user.name t
	git -C "$WORK" commit -q --allow-empty -m base
	git -C "$WORK" branch -q -M main
	git -C "$WORK" update-ref refs/remotes/origin/main HEAD
	git -C "$WORK" symbolic-ref refs/remotes/origin/HEAD refs/remotes/origin/main
	git -C "$WORK" checkout -q -b feature
	git -C "$WORK" commit -q --allow-empty -m "work"
}

# existující PR → nevypíše nic
setup_pr_repo
MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/gh" << 'GHEOF'
#!/bin/bash
case "${1:-}" in
	api) echo "testuser" ;;
	pr)
		case "${2:-}" in
			list)   echo '[{"url":"https://github.com/org/r/pull/1"}]' ;;
			create) echo "https://github.com/org/r/pull/2" ;;
		esac
		;;
esac
GHEOF
chmod +x "$MOCK_DIR/gh"
cat > "$MOCK_DIR/git" << 'GITEOF'
#!/bin/bash
for a in "$@"; do
	if [ "$a" = "push" ]; then exit 0; fi
done
exec /usr/bin/git "$@"
GITEOF
chmod +x "$MOCK_DIR/git"

out=$(echo "tělo" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-workspace" \
	pr --work-dir="$WORK" --repo=org/r --title=T 2>/dev/null)
assert_eq "pr: existující PR nevypíše nic" "" "$out"
rm -rf "$WORK" "$MOCK_DIR"

# nový PR → vypíše URL
setup_pr_repo
MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/gh" << 'GHEOF'
#!/bin/bash
case "${1:-}" in
	api) echo "testuser" ;;
	pr)
		case "${2:-}" in
			list)   echo '[]' ;;
			create) echo "https://github.com/org/r/pull/2" ;;
		esac
		;;
esac
GHEOF
chmod +x "$MOCK_DIR/gh"
cat > "$MOCK_DIR/git" << 'GITEOF'
#!/bin/bash
for a in "$@"; do
	if [ "$a" = "push" ]; then exit 0; fi
done
exec /usr/bin/git "$@"
GITEOF
chmod +x "$MOCK_DIR/git"

out=$(echo "tělo" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-workspace" \
	pr --work-dir="$WORK" --repo=org/r --title=T 2>/dev/null)
assert_eq "pr: nový PR vypíše URL" "https://github.com/org/r/pull/2" "$out"
rm -rf "$WORK" "$MOCK_DIR"

# nic k pushnutí → nevypíše nic
WORK=$(mktemp -d)
git -C "$WORK" init -q
git -C "$WORK" config user.email t@t
git -C "$WORK" config user.name t
git -C "$WORK" commit -q --allow-empty -m base
git -C "$WORK" branch -q -M main
git -C "$WORK" update-ref refs/remotes/origin/main HEAD
git -C "$WORK" symbolic-ref refs/remotes/origin/HEAD refs/remotes/origin/main
git -C "$WORK" checkout -q -b feature

out=$(echo "tělo" | "$SCRIPTS_DIR/olw-workspace" \
	pr --work-dir="$WORK" --repo=org/r --title=T 2>/dev/null)
assert_eq "pr: nic k pushnutí nevypíše nic" "" "$out"
rm -rf "$WORK"

summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-workspace.sh`
Expected: FAIL — dnešní `init` bere adresář pozičně a čte payload ze stdin, `pr` vypisuje payload s `prUrl` i pro existující PR.

- [ ] **Step 3: Přepiš `olw-workspace`**

Nahraď celý obsah souboru `olw-workspace`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

cmd="${1:-}"
shift || true

case "$cmd" in
	init)
		ROOT=""
		PROJECT=""
		REPO=""
		BRANCH=""

		while [ $# -gt 0 ]; do
			case "$1" in
				--root=*)    ROOT="${1#*=}" ;;
				--project=*) PROJECT="${1#*=}" ;;
				--repo=*)    REPO="${1#*=}" ;;
				--branch=*)  BRANCH="${1#*=}" ;;
				*) die "unknown argument: $1" ;;
			esac
			shift
		done

		if [ -z "$ROOT" ]; then
			die "--root= is required"
		fi

		if [ -z "$PROJECT" ]; then
			die "--project= is required"
		fi

		DIR_NAME=$("$(dirname "$0")/olw-text" webalize "$PROJECT")

		if [ -z "$REPO" ]; then
			WORK_DIR="$ROOT/$DIR_NAME"
			mkdir -p "$WORK_DIR"
		else
			WORK_DIR="$ROOT/repos/$DIR_NAME"
			mkdir -p "$ROOT/repos"

			FORK_NAME="${REPO//\//-}"
			FORK_NAME="${FORK_NAME,,}"

			GH_USER=$(gh api user --jq '.login')

			gh repo fork "$REPO" --fork-name "$FORK_NAME" >/dev/null 2>&1 || true

			gh repo sync "$GH_USER/$FORK_NAME" --source "$REPO" >&2 || \
				die "failed to sync fork '$GH_USER/$FORK_NAME'"

			if [ -d "$WORK_DIR/.git" ]; then
				REMOTE_URL=$(git -C "$WORK_DIR" remote get-url origin 2>/dev/null || echo "")

				if [[ "$REMOTE_URL" != *"$FORK_NAME"* ]]; then
					die "directory '$WORK_DIR' has wrong remote: $REMOTE_URL"
				fi

				git -C "$WORK_DIR" fetch origin >&2
			elif [ -d "$WORK_DIR" ]; then
				die "directory '$WORK_DIR' exists but is not a git repository"
			else
				gh repo clone "$GH_USER/$FORK_NAME" "$WORK_DIR" >&2 || \
					die "failed to clone '$GH_USER/$FORK_NAME' into '$WORK_DIR'"
			fi

			if [ -n "$BRANCH" ]; then
				DEFAULT_BRANCH=$(git -C "$WORK_DIR" symbolic-ref refs/remotes/origin/HEAD 2>/dev/null || echo "refs/remotes/origin/main")
				DEFAULT_BRANCH="${DEFAULT_BRANCH#refs/remotes/origin/}"

				if git -C "$WORK_DIR" rev-parse --verify "$BRANCH" >/dev/null 2>&1; then
					git -C "$WORK_DIR" checkout "$BRANCH" >&2
				elif git -C "$WORK_DIR" rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
					git -C "$WORK_DIR" checkout -b "$BRANCH" "origin/$BRANCH" >&2
				else
					git -C "$WORK_DIR" checkout -b "$BRANCH" "origin/$DEFAULT_BRANCH" >&2
				fi
			fi
		fi

		realpath "$WORK_DIR"
		;;

	finish)
		WORK_DIR=""
		REPO=""
		MESSAGE=""

		while [ $# -gt 0 ]; do
			case "$1" in
				--work-dir=*) WORK_DIR="${1#*=}" ;;
				--repo=*)     REPO="${1#*=}" ;;
				--message=*)  MESSAGE="${1#*=}" ;;
				*) die "unknown argument: $1" ;;
			esac
			shift
		done

		# Bez repozitáře není kam pushovat — karta se zpracovává lokálně.
		if [ -z "$REPO" ]; then
			exit 0
		fi

		if [ -z "$WORK_DIR" ]; then
			die "--work-dir= is required"
		fi

		if [ -z "$MESSAGE" ]; then
			die "--message= is required"
		fi

		cd "$WORK_DIR" || die "work dir not found: $WORK_DIR"

		BRANCH=$(git branch --show-current)

		if [ -n "$(git status --porcelain)" ]; then
			{ git add -A && git commit -m "$MESSAGE"; } >&2
		fi

		DEFAULT_BRANCH=$(git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed 's|refs/remotes/origin/||' || echo main)
		AHEAD=$(git rev-list --count "origin/${DEFAULT_BRANCH}..HEAD" 2>/dev/null || echo 0)

		if [ "$AHEAD" -eq 0 ]; then
			exit 0
		fi

		git push -u origin "$BRANCH" >&2
		;;

	pr)
		WORK_DIR=""
		REPO=""
		TITLE=""

		while [ $# -gt 0 ]; do
			case "$1" in
				--work-dir=*) WORK_DIR="${1#*=}" ;;
				--repo=*)     REPO="${1#*=}" ;;
				--title=*)    TITLE="${1#*=}" ;;
				*) die "unknown argument: $1" ;;
			esac
			shift
		done

		# Stdin se čte vždycky, i když se pak nic nedělá — volající ho posílá
		# bez ohledu na to, jestli repozitář existuje.
		BODY=$(cat)

		if [ -z "$REPO" ]; then
			exit 0
		fi

		if [ -z "$WORK_DIR" ]; then
			die "--work-dir= is required"
		fi

		if [ -z "$TITLE" ]; then
			die "--title= is required"
		fi

		cd "$WORK_DIR" || die "work dir not found: $WORK_DIR"

		BRANCH=$(git branch --show-current)

		DEFAULT_BRANCH=$(git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed 's|refs/remotes/origin/||' || echo main)
		AHEAD=$(git rev-list --count "origin/${DEFAULT_BRANCH}..HEAD" 2>/dev/null || echo 0)

		if [ "$AHEAD" -eq 0 ]; then
			exit 0
		fi

		git push -u origin "$BRANCH" >&2

		GH_USER=$(gh api user --jq '.login')
		EXISTING_URL=$(gh pr list --repo "$REPO" --head "${GH_USER}:${BRANCH}" --json url 2>/dev/null | jq -r '.[0].url // empty' || true)

		# URL se vypisuje jen u PR, který jsme právě založili. Volající z toho
		# pozná, jestli má o odkazu informovat — u existujícího PR už to
		# jednou udělal.
		if [ -n "$EXISTING_URL" ]; then
			exit 0
		fi

		gh pr create --repo "$REPO" --title "$TITLE" --body "$BODY" --head "${GH_USER}:${BRANCH}"
		;;

	*)
		echo "Usage: olw-workspace <command> [<args>]" >&2
		echo "" >&2
		echo "Commands:" >&2
		echo "  init --root= --project= [--repo=] [--branch=]  - prepare workspace, print its path" >&2
		echo "  finish --work-dir= [--repo=] --message=        - commit and push the branch" >&2
		echo "  pr --work-dir= [--repo=] --title=              - open a PR (body on stdin); prints the URL only if it created one" >&2
		exit 1
		;;
esac
```

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-workspace.sh`
Expected: PASS.

- [ ] **Step 5: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde.

- [ ] **Step 6: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-workspace tests/test-olw-workspace.sh
git commit -m "olw-workspace: named arguments, path on stdout, PR url only when created"
```

---

### Task 6: `olw-agent`

Poslední skript s obálkou. `--model=` bere model přímo (`sonnet`), ne jméno
agenta (`claude-sonnet`) — tak to předává kámen v donutu.

**Files:**
- Modify: `olw-agent` (celý)
- Test: `tests/test-olw-agent.sh` (celý)

**Interfaces:**
- Consumes: text promptu, který produkuje `olw-task format-prompt` z Tasku 4.
- Produces: `olw-agent --model=<model> [--system-prompt-file=<path>] [--work-dir=<path>]` — stdin prompt, stdout odpověď agenta. Modely: `opus`, `sonnet`, `haiku`, `dummy`.

- [ ] **Step 1: Přepiš testovou sadu**

Nahraď celý obsah `tests/test-olw-agent.sh`:

```bash
#!/bin/bash
set -euo pipefail

source "$(dirname "$0")/test-helper.sh"

SCRIPTS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

PROMPT_FILE=$(mktemp)
echo "Always commit your changes." > "$PROMPT_FILE"

# --- rozklad argumentů ---

exit_code=0
echo "prompt" | "$SCRIPTS_DIR/olw-agent" >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "chybějící --model je chyba" "$exit_code"

exit_code=0
echo "prompt" | "$SCRIPTS_DIR/olw-agent" --model=bogus >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "neznámý model je chyba" "$exit_code"

exit_code=0
echo "prompt" | "$SCRIPTS_DIR/olw-agent" --model=dummy --bogus=1 >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "neznámý argument je chyba" "$exit_code"

exit_code=0
echo "prompt" | "$SCRIPTS_DIR/olw-agent" --model=dummy --system-prompt-file=/nonexistent/f.txt >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "předaný a neexistující systémový prompt je chyba" "$exit_code"

# --- dummy ---

out=$(echo "prompt" | "$SCRIPTS_DIR/olw-agent" --model=dummy)
assert_eq "dummy nic nevypíše" "" "$out"

exit_code=0
echo "prompt" | "$SCRIPTS_DIR/olw-agent" --model=dummy >/dev/null 2>&1 || exit_code=$?
assert_exit_0 "dummy končí nulou" "$exit_code"

# --- volání claude ---

MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/claude" << 'CLEOF'
#!/bin/bash
echo "args: $*" >> "$ARGS_LOG"
cat > /dev/null
echo "Mocked agent response"
CLEOF
chmod +x "$MOCK_DIR/claude"
export ARGS_LOG="$MOCK_DIR/args"
: > "$ARGS_LOG"

out=$(echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" \
	--model=sonnet --system-prompt-file="$PROMPT_FILE")
assert_eq "odpověď agenta jde na stdout" "Mocked agent response" "$out"
assert_eq "model se předá claudovi beze změny" "true" \
	"$(grep -q -- '--model sonnet' "$ARGS_LOG" && echo true || echo false)"
assert_eq "systémový prompt se předá" "true" \
	"$(grep -q -- "--append-system-prompt-file $PROMPT_FILE" "$ARGS_LOG" && echo true || echo false)"

# nepředaný systémový prompt = spustit bez něj, ne chyba
: > "$ARGS_LOG"
out=$(echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" --model=sonnet)
assert_eq "bez systémového promptu proběhne" "Mocked agent response" "$out"
assert_eq "bez systémového promptu se přepínač nepředá" "false" \
	"$(grep -q -- '--append-system-prompt-file' "$ARGS_LOG" && echo true || echo false)"
rm -rf "$MOCK_DIR"

# --- prompt se předává na stdin ---

MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/claude" << 'CLEOF'
#!/bin/bash
cat
CLEOF
chmod +x "$MOCK_DIR/claude"
out=$(echo "tohle je prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" --model=haiku)
assert_eq "prompt jde claudovi na stdin" "tohle je prompt" "$out"
rm -rf "$MOCK_DIR"

# --- work-dir ---

WORK_DIR=$(mktemp -d)
MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/claude" << 'CLEOF'
#!/bin/bash
cat > /dev/null
pwd
CLEOF
chmod +x "$MOCK_DIR/claude"

out=$(echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" \
	--model=sonnet --work-dir="$WORK_DIR")
assert_eq "agent běží ve --work-dir" "$(realpath "$WORK_DIR")" "$(realpath "$out")"

exit_code=0
echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" \
	--model=sonnet --work-dir=/nonexistent/dir >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "neexistující --work-dir je chyba" "$exit_code"
rm -rf "$WORK_DIR" "$MOCK_DIR"

# --- selhání claude ---

MOCK_DIR=$(mktemp -d)
cat > "$MOCK_DIR/claude" << 'CLEOF'
#!/bin/bash
cat > /dev/null
echo "Failed to authenticate" >&2
exit 1
CLEOF
chmod +x "$MOCK_DIR/claude"

exit_code=0
echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" --model=sonnet >/dev/null 2>&1 || exit_code=$?
assert_exit_nonzero "selhání claude končí nenulově" "$exit_code"

err=$(echo "prompt" | PATH="$MOCK_DIR:$PATH" "$SCRIPTS_DIR/olw-agent" --model=sonnet 2>&1 >/dev/null || true)
assert_eq "selhání claude hlásí na stderr" "true" \
	"$(echo "$err" | grep -q '^Error: ' && echo true || echo false)"
rm -rf "$MOCK_DIR"

rm -f "$PROMPT_FILE"
summary
```

- [ ] **Step 2: Spusť sadu a ověř, že padá**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-agent.sh`
Expected: FAIL — dnešní skript bere agenta a soubor pozičně a čte prompt z payloadu.

- [ ] **Step 3: Přepiš `olw-agent`**

Nahraď celý obsah souboru `olw-agent`:

```bash
#!/bin/bash
set -euo pipefail

die() { echo "Error: $1" >&2; exit 1; }

MODEL=""
SYSTEM_PROMPT_FILE=""
WORK_DIR=""

while [ $# -gt 0 ]; do
	case "$1" in
		--model=*)              MODEL="${1#*=}" ;;
		--system-prompt-file=*) SYSTEM_PROMPT_FILE="${1#*=}" ;;
		--work-dir=*)           WORK_DIR="${1#*=}" ;;
		*) die "unknown argument: $1" ;;
	esac
	shift
done

# Stdin se čte vždycky, i pro dummy — volající ho posílá bez ohledu na model.
PROMPT=$(cat)

if [ -z "$MODEL" ]; then
	die "--model= is required"
fi

# Nepředaný systémový prompt znamená spustit agenta bez něj — donut zahodí
# celou skupinu argumentů, jejíž proměnná je prázdná. Předaný a neexistující
# je naproti tomu chyba.
#
# Kontrola musí být PŘED rozskokem podle modelu: platnost argumentu nesmí
# záviset na tom, jestli se pak volá skutečný agent, jinak by ji `dummy`
# přeskočil a chyba by se objevila až v ostrém běhu.
if [ -n "$SYSTEM_PROMPT_FILE" ] && [ ! -f "$SYSTEM_PROMPT_FILE" ]; then
	die "system prompt file not found: $SYSTEM_PROMPT_FILE"
fi

case "$MODEL" in
	opus|sonnet|haiku) ;;
	dummy) exit 0 ;;
	*) die "unknown model '$MODEL' (expected: opus, sonnet, haiku, dummy)" ;;
esac

if [ -n "$WORK_DIR" ]; then
	cd "$WORK_DIR" || die "work dir not found: $WORK_DIR"
fi

CLAUDE_ARGS=(--dangerously-skip-permissions --model "$MODEL")

if [ -n "$SYSTEM_PROMPT_FILE" ]; then
	CLAUDE_ARGS+=(--append-system-prompt-file "$SYSTEM_PROMPT_FILE")
fi

printf '%s' "$PROMPT" | claude "${CLAUDE_ARGS[@]}" -p || \
	die "claude invocation failed (model: $MODEL)"
```

- [ ] **Step 4: Spusť sadu a ověř, že prochází**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && tests/test-olw-agent.sh`
Expected: PASS.

- [ ] **Step 5: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK`, sada projde.

- [ ] **Step 6: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add olw-agent tests/test-olw-agent.sh
git commit -m "olw-agent: named arguments, prompt on stdin, answer on stdout"
```

---

### Task 7: Smazat `olw-lib.sh` a uzavřít přepis

Poslední krok, protože teprve teď ho nikdo nesourcuje. Zároveň se aktualizuje
README olw repa, které popisuje protokol s obálkou.

**Files:**
- Delete: `olw-lib.sh`
- Delete: `tests/test-olw-lib.sh`
- Modify: `tests/test-all.sh`
- Modify: `README.md`

**Interfaces:**
- Consumes: Tasky 1–6 musí být hotové — knihovna může zmizet teprve, když ji žádný skript nesourcuje.
- Produces: nic.

- [ ] **Step 1: Ověř, že knihovnu nikdo nesourcuje**

Run:
```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
grep -rn 'olw-lib\|olw_read\|olw_die\|olw_error_envelope\|OLW_STAGE\|OLW_INPUT\|OLW_PAYLOAD' \
	olw-* tests/ README.md Makefile 2>/dev/null
```
Expected: jediné zásahy jsou v `olw-lib.sh`, `tests/test-olw-lib.sh` a v README.
Když se ozve některý přepsaný skript, **nemazej nic** — vrať se a doplň
chybějící přepis.

- [ ] **Step 2: Smaž knihovnu a její test**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git rm olw-lib.sh tests/test-olw-lib.sh
```

V `tests/test-all.sh` smaž řádek `run_suite "$BIN_DIR/test-olw-lib.sh"`.

- [ ] **Step 3: Uprav README**

`README.md` má sekci `## Error handling in pipelines` (řádky 25–41), která
popisuje protokol s obálkou. Nahraď **celou tu sekci** včetně nadpisu:

```markdown
## Conventions

Each `olw-*` command stands on its own: input comes from arguments and stdin,
output goes to stdout. Nothing is passed between commands but the data itself —
there is no shared payload and no error envelope.

On failure a command prints `Error: <message>` to **stderr**, writes nothing to
stdout, and exits non-zero. In a pipeline, use `set -o pipefail` to notice.

Commands that donut invokes as blocks take `--key=value` arguments, because
donut drops an entire argument group whose variable is empty — that is how
optional inputs work. The rest take positional arguments.
```

Pak projdi sekce jednotlivých nástrojů a oprav v nich řádky s použitím a popis
výstupu podle nového rozhraní:

| sekce | nový tvar |
|---|---|
| `### olw-agent` | `olw-agent --model=<model> [--system-prompt-file=<path>] [--work-dir=<path>]`; prompt na stdin, odpověď na stdout; modely `opus`, `sonnet`, `haiku`, `dummy` |
| `### olw-trello` | wrapper nad API — výchozí výstup je JSON z API, `--text` dává skalární tvar; `card <shortId>` vrací kartu včetně komentářů, příloh a checklistů; `comment-add` bere text argumentem nebo ze stdin |
| `### olw-task` | `format-prompt` (task JSON → text promptu), `repo-find --project=<name>` (bez stdin, `owner/name` nebo nic), `project-detect` (task JSON → task JSON). **`status-assert` smaž — už neexistuje.** |
| `### olw-workspace` | `init --root= --project= [--repo=] [--branch=]` vypíše cestu k workDir; `finish --work-dir= [--repo=] --message=`; `pr --work-dir= [--repo=] --title=` s tělem na stdin, vypíše URL jen když PR sám založil |
| `### olw-json` | argumenty beze změny, jen mizí obálka — čte JSON ze stdin, vypisuje JSON na stdout |
| `### olw-trello-to-task` | `olw-trello-to-task [--my-id=<id>]`; na stdin karta z `olw-trello card`, na stdout **holý task JSON** bez obalu `olw_task` |

`### olw-text` a sekce `## Installation` nech beze změny — jich se přepis
netýká.

- [ ] **Step 3b: Ověř, že v README nezůstala zmínka o obálce**

Run:
```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
grep -n -i 'envelope\|payload\|status-assert\|olw-lib' README.md
```
Expected: žádný výstup.

- [ ] **Step 4: Spusť celou sadu a syntaktickou kontrolu**

Run: `cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw && make check && make test`
Expected: `syntax OK` a sada projde. Počet skriptů v hlášce `make check` klesne
o jeden — `olw-lib.sh` odpovídal masce `olw-*` a linkoval se i do `~/bin`,
což byla drobná vada, kterou jeho smazání odstraní.

- [ ] **Step 5: Commit**

```bash
cd /home/honza/Dokumenty/Projekty/donut-org/donut/docs/workflows/olw
git add -u olw-lib.sh tests/test-olw-lib.sh tests/test-all.sh README.md
git commit -m "Drop olw-lib.sh: the envelope protocol is gone"
```

---

## Ověření na ostro

Tohle **není task** — nejde to udělat ze session, protože to sahá na skutečnou
Trello kartu a skutečný GitHub. Patří sem ale jako poslední krok přepisu.

Bashová sada ověřuje jednotlivé příkazy, ne to, že si s donutem sedí argumenty.
To prověří teprve:

```bash
DONUT=/home/honza/Dokumenty/Projekty/donut-org/donut

make -C "$DONUT/docs/workflows/olw" install   # symlinky nových skriptů do ~/bin
cd "$DONUT/docs/workflows/donut"              # tady jsou blocks/ a workflows/

donut card-dev --shortId=… --expectStatus=… --model=sonnet \
               --systemPrompt=… --targetList=… --tag='#developer' \
               --workRoot=… --curlrc=…
```

Když některý kámen spadne na neznámém argumentu, sedí spolu špatně **kámen
a skript** — a kámen je v donutu, ne tady.
