<?php

declare(strict_types=1);

namespace App\WhiteList;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WhiteListClientSelector
{
    public function __construct(
        private readonly MfWhiteListClient $mf,
        private readonly FakeWhiteListClient $fake,
        #[Autowire('%env(WHITE_LIST_CLIENT)%')]
        private readonly string $mode,
    ) {
    }

    public function select(): WhiteListClient
    {
        return 'fake' === $this->mode ? $this->fake : $this->mf;
    }
}
