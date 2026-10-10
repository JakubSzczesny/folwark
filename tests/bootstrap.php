<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

date_default_timezone_set('Europe/Warsaw');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
