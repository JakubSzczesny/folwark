<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Contract;
use App\Entity\Contractor;
use App\Entity\ContractorHistory;
use App\Entity\InvoiceItem;
use App\Entity\Unit;
use App\Enum\InvoiceKind;
use App\Setup\StartupData;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class ContractorAndContractTest extends FunctionalTestCase
{
    public function testManualEditLocksDataAndCanBeUnlocked(): void
    {
        $contractor = ContractorFactory::createOne(['nip' => '7311000000', 'name' => 'Petromex', 'city' => 'Toruń']);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/kontrahenci/'.$contractor->getId().'/edycja');
        $this->client->submitForm('Zapisz', ['contractor[city]' => 'Bydgoszcz']);
        self::assertResponseRedirects('/kontrahenci/'.$contractor->getId());

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        $fresh = $em->find(Contractor::class, $contractor->getId());
        self::assertTrue($fresh?->isUpdateLocked());
        self::assertSame(2, $em->getRepository(ContractorHistory::class)->count(['contractor' => $fresh]));

        $crawler = $this->client->request('GET', '/kontrahenci/'.$contractor->getId());
        self::assertSelectorTextContains('main', 'Blokada aktualizacji');
        $this->client->submit($crawler->filter('form[action$="/odblokuj"]')->form());
        $em->clear();
        self::assertFalse($em->find(Contractor::class, $contractor->getId())?->isUpdateLocked());
    }

    public function testManualContractorWithoutNip(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/kontrahenci/nowy');
        $this->client->submitForm('Zapisz', ['contractor[name]' => 'Sąsiad Jan Nowak', 'contractor[country]' => 'PL']);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Sąsiad Jan Nowak');
    }

    public function testInvalidNipIsRejected(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/kontrahenci/nowy');
        $this->client->submitForm('Zapisz', ['contractor[name]' => 'Firma', 'contractor[nip]' => '123-456-78-90', 'contractor[country]' => 'PL']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Nieprawidłowy NIP');
    }

    public function testContractRealizationFromInvoiceItems(): void
    {
        $this->useFarm();
        $this->service(StartupData::class)->seed();
        $em = $this->service(EntityManagerInterface::class);
        $tons = $em->getRepository(Unit::class)->findOneBy(['symbol' => 't']);
        $contract = ContractFactory::createOne(['contractedQuantity' => '200', 'quantityUnit' => $tons]);

        foreach (['120.5', '21800'] as $index => $quantity) {
            $invoice = InvoiceFactory::createOne(['kind' => InvoiceKind::Income, 'contract' => $contract]);
            $item = (new InvoiceItem($invoice, 1, 'Pszenica'))->setValues($quantity, 0 === $index ? 't' : 'kg', '800', '1000', '5');
            $invoice->addItem($item);
            $em->persist($item);
        }
        $em->flush();

        $this->loginAs(UserFactory::accountant());
        $this->client->request('GET', '/umowy/'.$contract->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '142,3 t z 200 t (71');
    }

    public function testContractExpiringNotificationAndFilter(): void
    {
        $owner = UserFactory::owner();
        ContractFactory::createOne(['number' => 'UM/EXP', 'validTo' => new \DateTimeImmutable('+10 days')]);
        ContractFactory::createOne(['number' => 'UM/LATER', 'validTo' => new \DateTimeImmutable('+90 days')]);

        $this->useFarm();
        $this->service(\App\Notification\DeadlineNotifications::class)->generate();
        $count = $this->service(EntityManagerInterface::class)->getRepository(\App\Entity\Notification::class)->count(['user' => $owner]);
        self::assertSame(1, $count);

        $this->loginAs($owner);
        $this->client->request('GET', '/umowy?wygasajace=1');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'UM/EXP');
    }

    public function testCreateContractWithAttachment(): void
    {
        $contractor = ContractorFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $file = tempnam(sys_get_temp_dir(), 'umowa');
        file_put_contents((string) $file, '%PDF-1.4 test');
        $crawler = $this->client->request('GET', '/umowy/nowa?kontrahent='.$contractor->getId());
        $form = $crawler->selectButton('Zapisz')->form([
            'contract[number]' => 'UK/1/2026',
            'contract[subject]' => 'Sprzedaż rzepaku',
            'contract[contractedQuantity]' => '50',
        ]);
        $upload = $form['contract[newAttachments][0]'];
        self::assertInstanceOf(FileFormField::class, $upload);
        $upload->upload((string) $file);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $contract = $this->service(EntityManagerInterface::class)->getRepository(Contract::class)->findOneBy(['number' => 'UK/1/2026']);
        self::assertNotNull($contract);
        self::assertCount(1, $contract->getAttachments());
    }
}
