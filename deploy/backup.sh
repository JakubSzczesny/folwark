#!/usr/bin/env bash
# Kopia zapasowa Folwarku: baza (zrzut SQL) + pliki (faktury XML, załączniki, zdjęcia) + .env.local.
# Trzyma 30 ostatnich kopii dziennych i 12 miesięcznych (kopia z 1. dnia miesiąca).
#
# Dostęp do bazy z pliku /etc/folwark/backup.cnf:
#   [client]
#   user=folwark
#   password=...
set -euo pipefail

APP_DIR="${APP_DIR:-/srv/folwark}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/folwark}"
DB_CONFIG="${DB_CONFIG:-/etc/folwark/backup.cnf}"
DB_NAME="${DB_NAME:-folwark}"
KEEP_DAILY="${KEEP_DAILY:-30}"
KEEP_MONTHLY="${KEEP_MONTHLY:-12}"

stamp="$(date +%Y-%m-%d)"
daily="$BACKUP_DIR/daily"
monthly="$BACKUP_DIR/monthly"
mkdir -p "$daily" "$monthly"
chmod 700 "$BACKUP_DIR"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

mariadb-dump --defaults-extra-file="$DB_CONFIG" --single-transaction --routines --triggers --default-character-set=utf8mb4 "$DB_NAME" \
    | gzip -9 > "$work/baza.sql.gz"

tar -C "$APP_DIR" -czf "$work/pliki.tar.gz" var/storage .env.local

target="$daily/folwark-$stamp.tar"
tar -C "$work" -cf "$target.part" baza.sql.gz pliki.tar.gz
mv "$target.part" "$target"
chmod 600 "$target"

if [ "$(date +%d)" = "01" ]; then
    cp "$target" "$monthly/folwark-$(date +%Y-%m).tar"
fi

# "|| true": pusty katalog (np. monthly przed 1. dniem miesiąca) to nie błąd mimo pipefail.
{ ls -1t "$daily"/folwark-*.tar 2>/dev/null || true; } | tail -n +"$((KEEP_DAILY + 1))" | xargs -r rm -f
{ ls -1t "$monthly"/folwark-*.tar 2>/dev/null || true; } | tail -n +"$((KEEP_MONTHLY + 1))" | xargs -r rm -f

echo "Kopia zapisana: $target ($(du -h "$target" | cut -f1))"
