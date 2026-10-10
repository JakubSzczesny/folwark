<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * W testach serwisy aplikacji są publiczne, żeby można je było pobrać z kontenera,
 * zanim zacznie ich używać jakikolwiek inny serwis (inaczej zostałyby usunięte jako nieużywane).
 */
final class PublicServicesForTestsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, 'App\\') && !$definition->isAbstract() && !$definition->hasErrors()) {
                $definition->setPublic(true);
            }
        }
    }
}
