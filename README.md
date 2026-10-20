# Folwark FMS

System zarządzania gospodarstwem rolnym: faktury z KSeF, kontrahenci i umowy, maszyny, przewidywanie zamówień, magazyn, kalendarz, analizy i zestawienia.

Aplikacja webowa w Symfony 7.4 (PHP 8.4), interfejs po polsku, działa na komputerze i telefonie. Jedna instalacja obsługuje kilka gospodarstw – każdy użytkownik widzi tylko te, do których należy.

## Uruchomienie lokalne

Wymagania: PHP 8.4 z rozszerzeniami `intl`, `sodium`, `gd`, `zip`, `exif`, `mbstring`, `pdo_mysql` (i `pdo_sqlite` do testów), Composer, MariaDB 10.11+ (albo MySQL 8), [Symfony CLI](https://symfony.com/download).

```bash
composer install
cp .env .env.local
```

W `.env.local` ustaw co najmniej:

```dotenv
DATABASE_URL="mysql://folwark:haslo@127.0.0.1:3306/folwark?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
APP_SECRET=dowolny-długi-losowy-ciąg
ENCRYPTION_KEY=dowolny-długi-losowy-ciąg   # szyfruje tokeny KSeF – nie zmieniaj po zapisaniu tokenu
KSEF_CLIENT=fake                           # fake = faktury z plików w var/ksef-fake, http = prawdziwy KSeF
```

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # dane demonstracyjne
symfony serve
```

`symfony serve` uruchamia też zadania w tle i kompilację stylów (`.symfony.local.yaml`). Losowy klucz można wygenerować tak: `php -r "echo bin2hex(random_bytes(32));"`.

Konta demonstracyjne (hasło `folwark-demo`): `wlasciciel@folwark.test`, `pracownik@folwark.test`, `ksiegowa@folwark.test`.

## Instalacja na serwerze

Opis dla Debiana 13 / Raspberry Pi OS (Trixie) / Ubuntu 24.04, aplikacja w `/srv/folwark`, użytkownik `www-data`.

1. Pakiety: `nginx`, `mariadb-server`, `php8.4-fpm` z rozszerzeniami jak wyżej, `composer`, `git`.
2. Baza i użytkownik:
   ```sql
   CREATE DATABASE folwark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'folwark'@'localhost' IDENTIFIED BY 'mocne-haslo';
   GRANT ALL ON folwark.* TO 'folwark'@'localhost';
   ```
3. Kod i konfiguracja:
   ```bash
   git clone https://github.com/JakubSzczesny/folwark.git /srv/folwark && cd /srv/folwark   # jako root – tak działa też automatyczna aktualizacja
   cp .env .env.local   # APP_ENV=prod, APP_SECRET, DATABASE_URL, ENCRYPTION_KEY, KSEF_CLIENT=http, MAILER_DSN, MAILER_FROM, DEFAULT_URI=https://folwark.local
   composer install --no-dev --optimize-autoloader
   php bin/console doctrine:migrations:migrate --no-interaction
   php bin/console tailwind:build --minify
   php bin/console asset-map:compile
   php bin/console app:install   # pierwsze konto właściciela i gospodarstwo
   chown -R www-data:www-data var
   ```
4. HTTPS także w sieci lokalnej: certyfikat np. z [mkcert](https://github.com/FiloSottile/mkcert) (`mkcert folwark.local`), pliki do `/etc/ssl/folwark/`. Konfiguracja nginx: `deploy/nginx/folwark.conf` → `/etc/nginx/sites-enabled/`. Urządzenia w sieci muszą znać nazwę `folwark.local` (wpis w routerze albo w `hosts`) i ufać certyfikatowi CA z mkcert.
5. Usługi systemd (niżej) i kopie zapasowe.

### Usługi systemd

| Usługa | Co robi |
| --- | --- |
| `php8.4-fpm`, `nginx` | aplikacja (pakiety systemowe) |
| `folwark-worker` | zadania w tle: synchronizacja KSeF, pobieranie XML, przeliczanie zamówień, e-maile |
| `folwark-scheduler` | harmonogram: KSeF co 5 min, zamówienia 5:30, terminy 6:00, dzienny skrót 6:05 |
| `folwark-backup.timer` | kopia zapasowa codziennie o 2:30 |
| `folwark-update.timer` | aktualizacja z GitHuba codziennie o 3:15 (gdy są nowe zmiany) |

```bash
cp deploy/systemd/folwark-* /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now folwark-worker folwark-scheduler folwark-backup.timer folwark-update.timer
```

Obie usługi kończą pracę co godzinę (`--time-limit=3600`) i są od razu uruchamiane ponownie – tak samo po błędzie. Awaria KSeF nie blokuje pracy ręcznej; nieudane zadania lądują w kolejce `failed` (`php bin/console messenger:failed:show`).

### Aktualizacja

Automatycznie: `folwark-update.timer` codziennie o 3:15 uruchamia `deploy/update.sh`. Gdy na gałęzi `main` w repozytorium jest coś nowego, skrypt:
1. robi kopię zapasową do `/var/backups/folwark/przed-aktualizacja` (5 ostatnich); bez udanej kopii nie aktualizuje,
2. zatrzymuje `folwark-worker` i `folwark-scheduler`,
3. pobiera kod (tylko przewinięcie – przy lokalnych zmianach albo przepisanej historii pomija aktualizację), instaluje zależności, buduje style i zasoby; błąd na tym etapie cofa kod do poprzedniej wersji,
4. wykonuje migracje, czyści cache, przeładowuje PHP-FPM i uruchamia usługi.

Błąd migracji zatrzymuje aktualizację z wypisaną instrukcją powrotu (kod + odtworzenie z kopii sprzed aktualizacji). Wszystko widać w `journalctl -u folwark-update`. Aktualizacja od razu: `systemctl start folwark-update`. Inną gałąź (np. osobną „produkcyjną”, do której trafiają tylko sprawdzone zmiany) ustawia się w `Environment=UPDATE_BRANCH=` w `/etc/systemd/system/folwark-update.service`. Nowe pakiety systemowe czy rozszerzenia PHP trzeba doinstalować ręcznie.

Ręcznie:

```bash
cd /srv/folwark
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console tailwind:build --minify && php bin/console asset-map:compile
php bin/console cache:clear
php bin/console messenger:stop-workers   # systemd uruchomi je z nowym kodem
```

## Kopie zapasowe

`deploy/backup.sh` robi codziennie jeden plik `folwark-RRRR-MM-DD.tar` z:
- zrzutem bazy (`baza.sql.gz`),
- plikami z `var/storage` (XML faktur, załączniki umów, zdjęcia maszyn) i `.env.local` (`pliki.tar.gz`).

Trzyma 30 ostatnich kopii dziennych (`/var/backups/folwark/daily`) i 12 miesięcznych – kopię z 1. dnia miesiąca (`/var/backups/folwark/monthly`).

Przygotowanie:

```bash
mkdir -p /etc/folwark
printf '[client]\nuser=folwark\npassword=mocne-haslo\n' > /etc/folwark/backup.cnf
chmod 600 /etc/folwark/backup.cnf
chmod +x /srv/folwark/deploy/*.sh
systemctl start folwark-backup   # pierwsza kopia od razu, żeby sprawdzić
```

Kopia zawiera `ENCRYPTION_KEY` – bez niego zapisany token KSeF jest nie do odczytania, więc kopie trzymaj w bezpiecznym miejscu. Warto je co jakiś czas kopiować poza serwer (dysk USB, NAS).

### Odtworzenie

```bash
/srv/folwark/deploy/restore.sh /var/backups/folwark/daily/folwark-2026-10-14.tar
```

Skrypt pyta o potwierdzenie, zatrzymuje usługi `folwark-*`, wgrywa bazę i pliki, wykonuje migracje, czyści cache i uruchamia usługi. Na nowym serwerze najpierw wykonaj instalację (kroki 1–4, bez `app:install`), potem odtworzenie. Odtwarzanie dobrze jest raz przećwiczyć na innym komputerze.

## Testy i jakość

```bash
composer test    # PHPUnit (baza SQLite w var/test.db, pliki w var/storage-test)
composer stan    # PHPStan, poziom 8
composer cs      # PHP-CS-Fixer (@Symfony), podgląd zmian
```

## Specyfikacja → kod

| Specyfikacja | Gdzie w kodzie |
| --- | --- |
| 2–3. UX, wygląd, menu, elementy wspólne | `templates/layout.html.twig`, `src/Ui/Navigation.php`, `assets/styles/app.css`, `assets/controllers/` (lista z klawiaturą, menu pod prawym przyciskiem, panel podglądu, filtry) |
| 3.5 Wyszukiwarka globalna | `src/Search/` |
| 5. Role i uprawnienia | `src/Security/Permission.php`, `PermissionVoter.php`; gospodarstwa: `src/Farm/` |
| 6. Pulpit | `src/Twig/Components/Dashboard.php`, `src/Dashboard/`, `src/Controller/DashboardController.php` |
| 7.1–7.4 KSeF, synchronizacja, klasyfikacja | `src/Ksef/` (`KsefSynchronizer`, `Http/`, `Fake/`, `Xml/`), `src/Invoice/InvoiceImporter.php`, `InvoiceClassifier.php`, `InvoiceXmlService.php` |
| 7.5–7.7 Lista, podgląd, pełny widok | `src/Controller/InvoiceController.php`, `src/Invoice/InvoiceFilter.php`, `templates/invoice/` |
| 7.8 Przypisanie towarów, „Tylko koszt”, reguły | `src/Controller/InvoiceAssignController.php`, `src/Product/ItemAssigner.php`, `ProductMatcher.php`, `UnitParser.php`, `src/Controller/Settings/InvoiceRuleController.php` |
| 8. Kontrahenci (NIP, Biała lista), umowy | `src/Contractor/`, `src/WhiteList/`, `src/Controller/ContractorController.php`, `ContractController.php`, `src/Contract/` |
| 9. Zamówienia – algorytm i ekran | `src/Order/OrderPredictor.php`, `OrderPlanner.php`, `src/Controller/OrderController.php` |
| 10. Maszyny, informacje, serwis | `src/Machine/`, `src/Tree/`, `src/Controller/Machine/` |
| Kalendarz – wydarzenia, powtarzanie, przypomnienia, terminy z modułów | `src/Calendar/` (`CalendarFeed`, `CalendarOccurrences`, `CalendarReminders`, `Source/`), `src/Controller/CalendarController.php`, `src/Security/CalendarEventVoter.php`, `templates/calendar/` |
| Notatki (wpisy z autorem i datą, edycja) przy kontrahentach, maszynach i fakturach | `src/Note/`, `src/Entity/Note.php`, `src/Controller/NoteController.php`, `src/Security/NoteVoter.php`, `templates/_partials/notes.html.twig` |
| Moduły – wyłączanie bez usuwania danych | `src/Module/` (`Modules`, `ModuleAccessSubscriber`), `src/Controller/Settings/ModuleController.php` (Ustawienia → Moduły, prawy przycisk na pozycji menu) |
| 11.2 Waga – lista do zrobienia | `src/Controller/WeighbridgeController.php` (11.3 – etap 6) |
| 12.2 Słowniki | `src/Controller/Admin/` (EasyAdmin, `/ustawienia/slowniki`), dane startowe `src/Setup/StartupData.php` |
| 12.3 Linki towaru | `src/Product/ProductLinks.php`, `src/Controller/Product/ProductLinkController.php` |
| 12.4 Dokumenty, ruchy, stany | `src/Entity/StockDocument*.php`, `StockMovement.php`, `Stock.php`, `src/Warehouse/StockLedger.php` (średnia ważona, INW, anulowanie, „Popraw”), `DocumentNumberer.php`, `InvoiceReceipt.php` („Utwórz też PZ”) |
| 12.5 Ekrany magazynu, karta towaru | `src/Controller/Warehouse/`, `src/Controller/Product/ProductController.php`, `src/Product/ProductPurchases.php`, `ProductMachines.php` |
| 13. Powiadomienia | `src/Notification/`, `src/Scheduler/MainSchedule.php`, `src/MessageHandler/` |
| 14. Analizy | `src/Analytics/`, `src/Controller/AnalyticsController.php` |
| 15. Zestawienia i eksporty | `src/Report/`, `src/Controller/ReportController.php`, `src/Export/` (XLSX, PDF) |
| BS Pay – przelewy w BS Bydgoszcz | `src/Payment/TransferData.php`, `src/Controller/PaymentController.php`, skrypt Tampermonkey `templates/bspay/userscript.js.twig` (instalacja: Ustawienia → BS Pay) |
| 16. Ustawienia | `src/Settings/`, `src/Controller/Settings/`, `src/Form/Settings/` |
| 17. Audyt | `src/Audit/AuditLogger.php`, dziennik w Ustawieniach |
| 17. Kopie, usługi | `deploy/` |
| 17. Testy | `tests/Unit/`, `tests/Functional/` |
