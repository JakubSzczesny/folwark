#!/usr/bin/env bash
# Automatyczna aktualizacja Folwarku z repozytorium git (uruchamia ją folwark-update.timer, ręcznie:
# systemctl start folwark-update, dziennik: journalctl -u folwark-update).
#
# Gdy na gałęzi UPDATE_BRANCH nie ma nic nowego – kończy bez zmian. Gdy jest:
#   1. kopia zapasowa do /var/backups/folwark/przed-aktualizacja (bez udanej kopii nie aktualizuje),
#   2. zatrzymanie usług folwark-worker i folwark-scheduler,
#   3. git pull (tylko przewinięcie), zależności, style, zasoby,
#   4. migracje bazy, czyszczenie cache, uruchomienie usług.
# Błąd w kroku 3 cofa kod do poprzedniej wersji. Błąd migracji zostawia instrukcję odtworzenia z kopii.
set -euo pipefail

APP_DIR="${APP_DIR:-/srv/folwark}"
UPDATE_BRANCH="${UPDATE_BRANCH:-main}"
APP_USER="${APP_USER:-www-data}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/folwark/przed-aktualizacja}"

export COMPOSER_ALLOW_SUPERUSER=1

git() { command git -c safe.directory="$APP_DIR" "$@"; }
console() { php bin/console --no-interaction "$@"; }
console_as_app() { runuser -u "$APP_USER" -- php bin/console --no-interaction "$@"; }
describe() { git log -1 --format='%h %s' "$1"; }

# Bez -e w środku (funkcja jest wywoływana w if), dlatego kroki połączone przez &&.
build() {
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress &&
        console tailwind:build --minify &&
        console asset-map:compile &&
        chown -R "$APP_USER:$APP_USER" var
}

start_services() {
    systemctl reload "$PHP_FPM_SERVICE" || true
    systemctl start folwark-worker folwark-scheduler || true
}

# Całość w funkcji: bash czyta skrypt w trakcie wykonywania, a git pull może podmienić ten plik.
main() {
    cd "$APP_DIR"

    git fetch --quiet origin "$UPDATE_BRANCH"
    local old new
    old="$(git rev-parse HEAD)"
    new="$(git rev-parse "origin/$UPDATE_BRANCH")"

    if [ "$old" = "$new" ]; then
        echo "Brak nowych zmian, wersja: $(describe "$old")."
        return 0
    fi
    if ! git merge-base --is-ancestor "$old" "$new"; then
        echo "origin/$UPDATE_BRANCH nie jest kontynuacją obecnej wersji ($(describe "$old")) – pomijam, potrzebna ręczna aktualizacja." >&2
        return 1
    fi
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "W $APP_DIR są lokalne zmiany w plikach z repozytorium – pomijam aktualizację:" >&2
        git status --short --untracked-files=no >&2
        return 1
    fi

    echo "Aktualizacja $(describe "$old") → $(describe "$new"):"
    git log --format='  %h %s' "$old..$new"

    BACKUP_DIR="$BACKUP_DIR" KEEP_DAILY=5 KEEP_MONTHLY=0 "$APP_DIR/deploy/backup.sh"

    systemctl stop folwark-worker folwark-scheduler || true

    git merge --ff-only --quiet "$new"
    if ! build; then
        echo "Błąd podczas budowania – powrót do $(describe "$old")." >&2
        git reset --hard --quiet "$old"
        build || echo "Nie udało się odbudować poprzedniej wersji – sprawdź ręcznie." >&2
        start_services
        return 1
    fi

    if ! console_as_app doctrine:migrations:migrate --allow-no-migration; then
        echo "Błąd migracji bazy. Usługi w tle zostają zatrzymane. Aby wrócić do poprzedniej wersji:" >&2
        echo "  cd $APP_DIR && git reset --hard $old && composer install --no-dev --optimize-autoloader && chown -R $APP_USER:$APP_USER var" >&2
        echo "  $APP_DIR/deploy/restore.sh $(ls -1t "$BACKUP_DIR"/daily/folwark-*.tar | head -n 1)" >&2
        return 1
    fi

    console_as_app cache:clear || echo "Nie udało się wyczyścić cache – sprawdź: php bin/console cache:clear." >&2
    start_services
    echo "Zaktualizowano do $(describe "$new")."
}

main "$@"
exit
