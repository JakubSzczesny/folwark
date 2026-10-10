<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Farm;
use App\Entity\User;
use App\Farm\FarmContext;
use App\Tests\Factory\FarmFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

abstract class FunctionalTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Loguje użytkownika i wybiera gospodarstwo (domyślnie testowe), tak jak zrobiłby to na ekranie wyboru.
     */
    protected function loginAs(User $user, ?Farm $farm = null): KernelBrowser
    {
        $this->client->loginUser($user);

        $farm ??= FarmFactory::testFarm();
        $crawler = $this->client->request('GET', '/gospodarstwa');
        $tile = $crawler->filter(\sprintf('form[action="/gospodarstwa/%d/wybierz"]', $farm->getId()));
        if ($tile->count() > 0) {
            $this->client->submit($tile->first()->form());
        }

        return $this->client;
    }

    /**
     * Kontekst gospodarstwa dla wywołań usług bezpośrednio z testu.
     */
    protected function useFarm(?Farm $farm = null): Farm
    {
        $farm ??= FarmFactory::testFarm();
        $this->service(FarmContext::class)->set($farm);

        return $farm;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class): object
    {
        $service = static::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
