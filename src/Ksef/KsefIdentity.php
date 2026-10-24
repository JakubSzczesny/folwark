<?php

declare(strict_types=1);

namespace App\Ksef;

use App\Farm\FarmContext;
use App\Ksef\Http\KsefEnvironment;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * NIP, w którego kontekście gospodarstwo łączy się z KSeF (z ustawień KSeF albo NIP gospodarstwa), i środowisko KSeF.
 */
final class KsefIdentity
{
    public function __construct(
        private readonly Settings $settings,
        private readonly FarmContext $context,
    ) {
    }

    public function nip(): ?string
    {
        return $this->settings->string(SettingKey::KSEF_NIP) ?? $this->context->getFarm()?->getNip();
    }

    public function environment(): KsefEnvironment
    {
        return KsefEnvironment::tryFrom((string) $this->settings->string(SettingKey::KSEF_ENVIRONMENT)) ?? KsefEnvironment::Test;
    }

    public function farmNip(): ?string
    {
        return $this->context->getFarm()?->getNip();
    }
}
