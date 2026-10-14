<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

/*
 * PHPStan ładuje aplikację ze swoimi klasami w pamięci – osobny cache, żeby nie psuć cache dev serwera.
 */
return new class($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']) extends Kernel {
    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/phpstan_'.$this->environment;
    }
};
