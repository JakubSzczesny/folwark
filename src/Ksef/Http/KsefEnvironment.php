<?php

declare(strict_types=1);

namespace App\Ksef\Http;

enum KsefEnvironment: string
{
    case Test = 'test';
    case Demo = 'demo';
    case Production = 'prod';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://api-test.ksef.mf.gov.pl/v2',
            self::Demo => 'https://api-demo.ksef.mf.gov.pl/v2',
            self::Production => 'https://api.ksef.mf.gov.pl/v2',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Testowe (dane fikcyjne)',
            self::Demo => 'Przedprodukcyjne DEMO (dane fikcyjne)',
            self::Production => 'Produkcyjne',
        };
    }
}
