<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Contractor;
use App\Entity\ContractorHistory;
use App\Entity\Invoice;
use App\Entity\InvoiceRule;
use App\Entity\KsefSync;
use App\Entity\Notification;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\NotificationType;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class KsefSyncTest extends FunctionalTestCase
{
    use KsefTestTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->em = $this->service(EntityManagerInterface::class);
        $this->configureFarm();
    }

    public function testNewInvoiceAppearsInCostTabWithCreatedContractor(): void
    {
        $owner = UserFactory::owner();
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-05-04', address: 'ul. Paliwowa 12')->item('Olej napędowy ON', 'l', 3000, 5.5));

        $this->syncKsef();

        $invoice = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'PET/1/2026']);
        self::assertNotNull($invoice);
        self::assertSame(InvoiceKind::Cost, $invoice->getKind());
        self::assertSame(InvoiceStatus::New, $invoice->getStatus());
        self::assertSame(self::SUPPLIER_NIP, $invoice->getContractor()?->getNip());
        self::assertSame('PETROMEX Sp. z o.o.', $invoice->getContractor()->getName());
        self::assertTrue($invoice->hasXml(), 'XML nowych kosztowych pobiera się automatycznie');
        self::assertCount(1, $invoice->getItems());
        self::assertSame('ul. Paliwowa 12', $invoice->getContractor()->getStreet());
        self::assertSame('2026-05-18', $invoice->getPaymentDue()?->format('Y-m-d'));

        $notifications = $this->em->getRepository(Notification::class)->findBy(['user' => $owner, 'type' => NotificationType::NewInvoices]);
        self::assertCount(1, $notifications);
        self::assertSame('1 nowa faktura kosztowa', $notifications[0]->getTitle());

        $this->loginAs($owner);
        $this->client->request('GET', '/faktury?rodzaj=kosztowe');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'PET/1/2026');
        self::assertSelectorTextContains('tbody', 'PETROMEX Sp. z o.o.');
        self::assertSelectorExists('tbody tr.is-unread');
        self::assertSelectorTextContains('.tabs .tab-active', '1');
    }

    public function testRepeatedSyncDoesNotDuplicateInvoices(): void
    {
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 100, 5));
        $this->addInvoice($this->costInvoice('PET/2/2026', '2026-05-25')->item('ON', 'l', 100, 5));

        $this->syncKsef();
        $this->syncKsef();
        $this->em->getRepository(Invoice::class)->findAll();

        self::assertSame(2, $this->em->getRepository(Invoice::class)->count([]));
        self::assertSame(1, $this->em->getRepository(Contractor::class)->count([]));
        self::assertSame(2, $this->em->getRepository(KsefSync::class)->count([]));
    }

    public function testNewerInvoiceUpdatesContractorAddressUnlessLocked(): void
    {
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-03-02', address: 'ul. Stara 1')->item('ON', 'l', 100, 5));
        $this->syncKsef();

        $contractor = $this->em->getRepository(Contractor::class)->findOneBy(['nip' => self::SUPPLIER_NIP]);
        self::assertSame('ul. Stara 1', $contractor?->getStreet());

        $this->addInvoice($this->costInvoice('PET/2/2026', '2026-04-10', sellerName: 'PETROMEX S.A.', address: 'ul. Nowa 5')->item('ON', 'l', 100, 5));
        $this->syncKsef();
        $this->em->refresh($contractor);

        self::assertSame('ul. Nowa 5', $contractor->getStreet());
        self::assertSame('PETROMEX S.A.', $contractor->getName());
        $fields = array_map(static fn (ContractorHistory $h): string => $h->getField(), $this->em->getRepository(ContractorHistory::class)->findBy(['contractor' => $contractor]));
        self::assertContains('street', $fields);
        self::assertContains('name', $fields);

        $contractor->setUpdateLocked(true);
        $this->em->flush();
        $this->addInvoice($this->costInvoice('PET/3/2026', '2026-05-10', address: 'ul. Najnowsza 9')->item('ON', 'l', 100, 5));
        $this->syncKsef();
        $this->em->refresh($contractor);

        self::assertSame('ul. Nowa 5', $contractor->getStreet(), 'Blokada chroni ręcznie wpisane dane');
    }

    public function testOlderInvoiceSyncedLaterDoesNotOverrideNewerData(): void
    {
        $this->addInvoice($this->costInvoice('PET/2/2026', '2026-04-10', address: 'ul. Nowa 5')->item('ON', 'l', 100, 5));
        $this->syncKsef();

        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-03-02', sellerName: 'Petromex stara nazwa', address: 'ul. Stara 1')->item('ON', 'l', 100, 5));
        $this->syncKsef();

        $contractor = $this->em->getRepository(Contractor::class)->findOneBy(['nip' => self::SUPPLIER_NIP]);
        self::assertSame('ul. Nowa 5', $contractor?->getStreet());
        self::assertSame('PETROMEX Sp. z o.o.', $contractor->getName());
    }

    public function testIncomeInvoiceAndRuleBasedCostOnly(): void
    {
        $energyNip = '6791000004';
        $this->em->persist((new InvoiceRule())->setContractorNip($energyNip));
        $this->em->flush();

        $this->fakeKsef()->add(\App\Ksef\Fake\Fa3Builder::invoice(self::FARM_NIP, 'Gospodarstwo testowe', '1131000001', 'Mleczarnia', 'FV/1/2026', new \DateTimeImmutable('2026-05-10'))
            ->item('Mleko surowe', 'l', 50000, 2.2, '5')->toXml(), new \DateTimeImmutable('2026-05-10 12:00'));
        $this->addInvoice($this->costInvoice('ENE/1/2026', '2026-05-05', $energyNip, 'Energa')->item('Energia', 'kWh', 5000, 0.7));

        $this->syncKsef();

        $income = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'FV/1/2026']);
        self::assertSame(InvoiceKind::Income, $income?->getKind());
        self::assertSame('1131000001', $income->getContractor()?->getNip());
        self::assertFalse($income->hasXml(), 'XML przychodowych pobiera się na żądanie');

        $energy = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'ENE/1/2026']);
        self::assertSame(InvoiceStatus::CostOnly, $energy?->getStatus());
        self::assertTrue($energy->isUnread());
    }

    public function testCorrectionIsLinkedToCorrectedInvoice(): void
    {
        $original = $this->addInvoice($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 3000, 5));
        $this->addInvoice($this->costInvoice('KOR/1/2026', '2026-05-20')
            ->correctionOf('PET/1/2026', $original, new \DateTimeImmutable('2026-05-04'))
            ->item('ON', 'l', 3000, 5, '23', true)
            ->item('ON', 'l', 2800, 5));

        $this->syncKsef();

        $correction = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'KOR/1/2026']);
        self::assertTrue($correction?->isCorrection());
        self::assertSame('PET/1/2026', $correction->getCorrectedInvoice()?->getNumber());
    }

    public function testErrorIsRecordedAndNotified(): void
    {
        $owner = UserFactory::owner();
        $settings = static::getContainer()->get(\App\Settings\Settings::class);
        $settings->set(\App\Settings\SettingKey::FARM_NIP, null);

        $this->syncKsef();

        $sync = $this->em->getRepository(KsefSync::class)->findOneBy([]);
        self::assertTrue($sync?->isFailed());
        self::assertSame(1, $this->em->getRepository(Notification::class)->count(['user' => $owner, 'type' => NotificationType::KsefError]));
    }
}
