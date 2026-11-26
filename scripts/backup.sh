#!/usr/bin/env bash
#
# Back up the Spravato database and the media library (docs/reports/restore-drill.md).
#
#   scripts/backup.sh                      # reads DB_* from .env, writes to $BACKUP_DIR
#   BACKUP_KEEP=30 scripts/backup.sh       # keep the newest 30 backups
#   DB_DATABASE=spravato_e2e BACKUP_DIR=/tmp/b scripts/backup.sh
#
# One backup is a directory spravato-<UTC timestamp>/ holding
#   database.sql.gz      mysqldump --single-transaction (consistent, no locks on
#                        InnoDB); no CREATE DATABASE / USE, so it restores under any name
#   storage-public.tar.gz storage/app/public (the media library)
#   MANIFEST             what was dumped, from where, row counts
#   SHA256SUMS           checked by scripts/restore.sh before it touches anything
#
# It never copies .env or APP_KEY. Consultation requests are encrypted with
# APP_KEY, so a backup without that key cannot be read back: store the key
# separately (password manager / secrets store), never next to the backups.
#
# With BACKUP_GPG_RECIPIENT set, each file is also encrypted to that public
# key (*.gpg) and the plain copies are removed: the dump holds health
# information, so off-site copies should always be encrypted.
#
# Environment (each falls back to .env, then to the default shown):
#   DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE DB_USERNAME DB_PASSWORD
#   BACKUP_DIR=<root>/storage/app/backups (git-ignored, never web-served)  BACKUP_KEEP=14
#   STORAGE_DIR=<root>/storage/app/public  BACKUP_GPG_RECIPIENT=
#
# Schedule (cron, as the deploy user):  15 2 * * *  /var/www/spravato/current/scripts/backup.sh >> /var/log/spravato-backup.log 2>&1

set -euo pipefail
umask 077

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Read one KEY from .env without sourcing it (values may hold shell syntax).
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
DB_DATABASE="$(setting DB_DATABASE)"
DB_USERNAME="$(setting DB_USERNAME root)"
DB_PASSWORD="$(setting DB_PASSWORD)"
BACKUP_DIR="$(setting BACKUP_DIR "$ROOT/storage/app/backups")"
BACKUP_KEEP="$(setting BACKUP_KEEP 14)"
STORAGE_DIR="$(setting STORAGE_DIR "$ROOT/storage/app/public")"
BACKUP_GPG_RECIPIENT="$(setting BACKUP_GPG_RECIPIENT)"

[[ -n "$DB_DATABASE" ]] || { echo "backup: DB_DATABASE is not set (env or .env)" >&2; exit 1; }
[[ "$BACKUP_KEEP" =~ ^[1-9][0-9]*$ ]] || { echo "backup: BACKUP_KEEP must be a positive number" >&2; exit 1; }

checksum() { if command -v sha256sum > /dev/null; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }

# The password goes in a 0600 option file, never on the command line (ps).
MYCNF="$(mktemp)"
trap 'rm -f "$MYCNF"; [[ -n "${WORK:-}" && -d "$WORK" ]] && rm -rf "$WORK"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "${DB_PASSWORD//\"/\\\"}" > "$MYCNF"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NAME="spravato-$STAMP"
mkdir -p "$BACKUP_DIR"
WORK="$BACKUP_DIR/.$NAME.partial"
mkdir "$WORK"

echo "backup: dumping $DB_DATABASE@$DB_HOST:$DB_PORT"
mysqldump --defaults-extra-file="$MYCNF" \
    --single-transaction --quick --routines --triggers \
    --no-tablespaces --hex-blob --default-character-set=utf8mb4 \
    "$DB_DATABASE" \
    | gzip -9 > "$WORK/database.sql.gz"

# Row counts, so a restore can be checked against the source.
mysql --defaults-extra-file="$MYCNF" --batch --skip-column-names "$DB_DATABASE" -e \
    "select table_name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE' order by table_name" \
    | while read -r table; do
        printf '%s\t%s\n' "$table" "$(mysql --defaults-extra-file="$MYCNF" --batch --skip-column-names "$DB_DATABASE" -e "select count(*) from \`$table\`")"
    done > "$WORK/ROWCOUNTS"

if [[ -d "$STORAGE_DIR" ]]; then
    echo "backup: archiving $STORAGE_DIR"
    tar -C "$STORAGE_DIR" -czf "$WORK/storage-public.tar.gz" .
else
    echo "backup: $STORAGE_DIR does not exist; no media archived" >&2
fi

{
    echo "created_at=$STAMP"
    echo "database=$DB_DATABASE"
    echo "host=$(hostname)"
    echo "storage_dir=$STORAGE_DIR"
    echo "git_commit=$(git -C "$ROOT" rev-parse --short HEAD 2> /dev/null || echo unknown)"
    echo "tables=$(wc -l < "$WORK/ROWCOUNTS" | tr -d ' ')"
    echo "note=Encrypted columns need this deployment's APP_KEY; it is not in this backup."
} > "$WORK/MANIFEST"

if [[ -n "$BACKUP_GPG_RECIPIENT" ]]; then
    for file in "$WORK"/database.sql.gz "$WORK"/storage-public.tar.gz; do
        [[ -f "$file" ]] || continue
        gpg --batch --yes --trust-model always --recipient "$BACKUP_GPG_RECIPIENT" --output "$file.gpg" --encrypt "$file"
        rm -f "$file"
    done
fi

(cd "$WORK" && checksum ./* > SHA256SUMS)
mv "$WORK" "$BACKUP_DIR/$NAME"
WORK=""
echo "backup: wrote $BACKUP_DIR/$NAME ($(du -sh "$BACKUP_DIR/$NAME" | cut -f1))"

# Keep the newest $BACKUP_KEEP; names sort by time.
backups=()
while IFS= read -r dir; do backups+=("$dir"); done < <(find "$BACKUP_DIR" -maxdepth 1 -type d -name 'spravato-*' | sort)
excess=$(( ${#backups[@]} - BACKUP_KEEP ))
for (( i = 0; i < excess; i++ )); do
    echo "backup: removing old ${backups[$i]}"
    rm -rf "${backups[$i]}"
done
