#!/usr/bin/env bash
# Odtworzenie Folwarku z kopii: ./restore.sh /var/backups/folwark/daily/folwark-2026-10-14.tar
# Nadpisuje bazę i pliki – usługi folwark-* są na ten czas zatrzymywane.
set -euo pipefail

APP_DIR="${APP_DIR:-/srv/folwark}"
DB_CONFIG="${DB_CONFIG:-/etc/folwark/backup.cnf}"
DB_NAME="${DB_NAME:-folwark}"
archive="${1:?Podaj plik kopii, np. /var/backups/folwark/daily/folwark-2026-10-14.tar}"

read -r -p "Odtworzyć $archive? Obecna baza i pliki zostaną zastąpione. [t/N] " answer
[ "$answer" = "t" ] || { echo "Przerwano."; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
tar -C "$work" -xf "$archive"

systemctl stop folwark-worker folwark-scheduler || true

gunzip -c "$work/baza.sql.gz" | mariadb --defaults-extra-file="$DB_CONFIG" "$DB_NAME"

rm -rf "$APP_DIR/var/storage"
tar -C "$APP_DIR" -xzf "$work/pliki.tar.gz"
chown -R www-data:www-data "$APP_DIR/var/storage"

cd "$APP_DIR"
sudo -u www-data APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
sudo -u www-data APP_ENV=prod php bin/console cache:clear

systemctl start folwark-worker folwark-scheduler
echo "Odtworzono z $archive."
