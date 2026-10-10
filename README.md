# Folwark

System zarządzania gospodarstwem rolnym: faktury z KSeF, kontrahenci i umowy, maszyny, przewidywanie zamówień, magazyn, analizy.

Aplikacja webowa w Symfony 7.4 (PHP 8.3+), interfejs po polsku, działa na komputerze i telefonie.

## Uruchomienie lokalne

Wymagania: PHP 8.3+ z rozszerzeniami `intl`, `sodium`, `gd`, `zip`, `pdo_mysql` (i `pdo_sqlite` do testów), Composer, MariaDB 10.11+ lub MySQL 8.

```bash
composer install
cp .env .env.local   # ustaw DATABASE_URL
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # dane demonstracyjne
php bin/console tailwind:build
symfony serve   # albo: php -S 127.0.0.1:8000 -t public
```

Konta demonstracyjne (hasło `folwark-demo`): `wlasciciel@folwark.test`, `pracownik@folwark.test`, `ksiegowa@folwark.test`.

## Testy i jakość

```bash
composer test    # PHPUnit (baza SQLite w var/test.db)
composer stan    # PHPStan, poziom 8
composer cs      # PHP-CS-Fixer (@Symfony), podgląd zmian
```
