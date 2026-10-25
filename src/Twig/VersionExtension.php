<?php

declare(strict_types=1);

namespace App\Twig;

use App\Version\Release;
use App\Version\ReleaseInfo;
use Twig\Attribute\AsTwigFunction;

final class VersionExtension
{
    public function __construct(
        private readonly ReleaseInfo $releaseInfo,
    ) {
    }

    #[AsTwigFunction('app_release')]
    public function release(): ?Release
    {
        return $this->releaseInfo->current();
    }
}
