<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\WeighbridgeTask;
use App\Setup\StartupData;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class WeighbridgeTest extends FunctionalTestCase
{
    public function testChecklistShowsStartupItemsAndProgress(): void
    {
        $this->service(StartupData::class)->seed();
        $this->loginAs(UserFactory::worker());

        $this->client->request('GET', '/waga');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Moduł wagi w przygotowaniu');
        self::assertSelectorTextContains('main', '0 / 14');
        self::assertSelectorTextContains('main', 'Terminal wagi (RHEWA 82c)');
    }

    public function testCheckingTaskStoresAuthorAndDate(): void
    {
        $this->service(StartupData::class)->seed();
        $worker = UserFactory::worker();
        $this->loginAs($worker);

        $crawler = $this->client->request('GET', '/waga');
        $this->client->submit($crawler->filter('form[action$="/odhacz"]')->first()->form());
        self::assertResponseRedirects('/waga');

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        $task = $em->getRepository(WeighbridgeTask::class)->findOneBy(['position' => 1]);

        self::assertNotNull($task);
        self::assertTrue($task->isDone());
        self::assertSame($worker->getId(), $task->getDoneBy()?->getId());
        self::assertEqualsWithDelta(time(), $task->getDoneAt()?->getTimestamp(), 5);

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '1 / 14');
        self::assertSelectorTextContains('main', $worker->getFullName());
    }

    public function testAccountantCannotCheckAndWorkerCannotEdit(): void
    {
        $this->service(StartupData::class)->seed();

        $this->loginAs(UserFactory::accountant());
        $crawler = $this->client->request('GET', '/waga');
        $this->client->submit($crawler->filter('form[action$="/odhacz"]')->first()->form());
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(UserFactory::worker());
        $this->client->request('GET', '/waga/nowy');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerAddsTask(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/waga/nowy');
        $this->client->submitForm('Zapisz', [
            'weighbridge_task[groupName]' => 'Kamery',
            'weighbridge_task[content]' => 'Zamówić uchwyt kamery',
            'weighbridge_task[position]' => '20',
        ]);

        self::assertResponseRedirects('/waga');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Zamówić uchwyt kamery');
    }
}
