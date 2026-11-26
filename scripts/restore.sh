#!/usr/bin/env bash
#
# Restore a backup made by scripts/backup.sh (docs/reports/restore-drill.md).
#
#   scripts/restore.sh <backup dir> --database <name> [--storage-dir <dir>] [--force]
#
#   --database     the database to load into; created if missing. It must be
#                  empty unless --force is given (then its tables are dropped).
#   --storage-dir  where to unpack the media library. Omit it to restore the
#                  database only. Restoring over a live storage/app/public:
#                  unpack to a new directory and swap it in.
#   --force        replace the contents of a non-empty database.
#
# Connection settings (DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD) come from
# the environment, then .env. *.gpg files are decrypted with the private key
# in the current user's keyring.
#
# Checksums are verified before anything is changed. Afterwards the row
# counts of the restored database are compared with the backup's ROWCOUNTS.
# The application can read encrypted consultation columns only with the
# APP_KEY of the deployment that wrote them.

set -euo pipefail
umask 077

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

usage() { sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//'; exit 2; }

BACKUP="" TARGET="" STORAGE="" FORCE=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --database) TARGET="${2:-}"; shift 2 ;;
        --storage-dir) STORAGE="${2:-}"; shift 2 ;;
        --force) FORCE=1; shift ;;
        -h | --help) usage ;;
        -*) echo "restore: unknown option $1" >&2; usage ;;
        *) BACKUP="$1"; shift ;;
    esac
done

[[ -n "$BACKUP" && -d "$BACKUP" ]] || { echo "restore: give the backup directory" >&2; usage; }
[[ "$TARGET" =~ ^[A-Za-z0-9_]+$ ]] || { echo "restore: --database must be a plain name (letters, digits, _)" >&2; exit 2; }

env_value() {
    local key="$1" file="$ROOT/.env" line
    [[ -f "$file" ]] || return 0
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%$'\r'}"
    if [[ "$line" =~ ^\"(.*)\"$ || "$line" =~ ^\'(.*)\'$ ]]; then line="${BASH_REMATCH[1]}"; fi
    printf '%s' "$line"
}

setting() {
    local key="$1" default="${2:-}"
    local value="${!key:-}"
    [[ -n "$value" ]] || value="$(env_value "$key")"
    printf '%s' "${value:-$default}"
}

DB_HOST="$(setting DB_HOST 127.0.0.1)"
DB_PORT="$(setting DB_PORT 3306)"
DB_USERNAME="$(setting DB_USERNAME root)"
DB_PASSWORD="$(setting DB_PASSWORD)"

MYCNF="$(mktemp)"
TMP="$(mktemp -d)"
trap 'rm -rf "$MYCNF" "$TMP"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "${DB_PASSWORD//\"/\\\"}" > "$MYCNF"
sql() { mysql --defaults-extra-file="$MYCNF" --batch --skip-column-names "$@"; }

echo "restore: verifying checksums in $BACKUP"
if command -v sha256sum > /dev/null; then (cd "$BACKUP" && sha256sum --check --quiet SHA256SUMS); else (cd "$BACKUP" && shasum -a 256 --check --quiet SHA256SUMS); fi

# Decrypt to a private temp directory when the backup is encrypted.
artifact() {
    local name="$1"
    if [[ -f "$BACKUP/$name" ]]; then
        printf '%s' "$BACKUP/$name"
    elif [[ -f "$BACKUP/$name.gpg" ]]; then
        gpg --batch --quiet --output "$TMP/$name" --decrypt "$BACKUP/$name.gpg"
        printf '%s' "$TMP/$name"
    fi
}

DUMP="$(artifact database.sql.gz)"
[[ -n "$DUMP" ]] || { echo "restore: no database.sql.gz in $BACKUP" >&2; exit 1; }

sql -e "create database if not exists \`$TARGET\` character set utf8mb4 collate utf8mb4_unicode_ci"
existing="$(sql -e "select count(*) from information_schema.tables where table_schema = '$TARGET'")"
if [[ "$existing" != "0" ]]; then
    if [[ "$FORCE" != "1" ]]; then
        echo "restore: $TARGET already has $existing tables; pass --force to replace them" >&2
        exit 1
    fi
    echo "restore: dropping $existing tables in $TARGET"
    sql -e "drop database \`$TARGET\`; create database \`$TARGET\` character set utf8mb4 collate utf8mb4_unicode_ci"
fi

echo "restore: loading $(basename "$DUMP") into $TARGET"
started=$(date +%s)
gunzip -c "$DUMP" | sql "$TARGET"
echo "restore: database loaded in $(( $(date +%s) - started ))s"

if [[ -f "$BACKUP/ROWCOUNTS" ]]; then
    mismatches=0
    while IFS=$'\t' read -r table expected; do
        actual="$(sql "$TARGET" -e "select count(*) from \`$table\`" 2> /dev/null || echo missing)"
        if [[ "$actual" != "$expected" ]]; then
            echo "restore: $table has $actual rows, backup recorded $expected" >&2
            mismatches=$(( mismatches + 1 ))
        fi
    done < "$BACKUP/ROWCOUNTS"
    tables=$(wc -l < "$BACKUP/ROWCOUNTS" | tr -d ' ')
    if [[ "$mismatches" == "0" ]]; then
        echo "restore: row counts match for all $tables tables"
    else
        echo "restore: $mismatches of $tables tables differ (expected only if the source was written to during the dump)" >&2
    fi
fi

if [[ -n "$STORAGE" ]]; then
    ARCHIVE="$(artifact storage-public.tar.gz)"
    if [[ -z "$ARCHIVE" ]]; then
        echo "restore: the backup has no storage archive" >&2
        exit 1
    fi
    mkdir -p "$STORAGE"
    tar -C "$STORAGE" -xzf "$ARCHIVE"
    echo "restore: media unpacked into $STORAGE ($(find "$STORAGE" -type f | wc -l | tr -d ' ') files)"
fi

echo "restore: done. Point DB_DATABASE at $TARGET and use the source deployment's APP_KEY."
