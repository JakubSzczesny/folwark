<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\PublicServicesForTestsPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        date_default_timezone_set('Europe/Warsaw');

        parent::boot();
    }

    protected function build(ContainerBuilder $container): void
    {
        if ('test' === $this->environment) {
            $container->addCompilerPass(new PublicServicesForTestsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        }
    }
}
