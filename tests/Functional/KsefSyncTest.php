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
use App\Ksef\Dto\MetadataQuery;
use App\Ksef\Fake\Fa3Builder;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\Http\HttpKsefClient;
use App\Ksef\KsefClientSelector;
use App\Ksef\KsefException;
use App\Ksef\KsefIdentity;
use App\Ksef\SubjectType;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\FarmFactory;
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
        self::assertTrue($invoice->hasXml(), 'XML nowych zakupowych pobiera się automatycznie');
        self::assertCount(1, $invoice->getItems());
        self::assertSame('ul. Paliwowa 12', $invoice->getContractor()->getStreet());
        self::assertSame('2026-05-18', $invoice->getPaymentDue()?->format('Y-m-d'));

        $notifications = $this->em->getRepository(Notification::class)->findBy(['user' => $owner, 'type' => NotificationType::NewInvoices]);
        self::assertCount(1, $notifications);
        self::assertSame('1 nowa faktura zakupowa', $notifications[0]->getTitle());

        $this->loginAs($owner);
        $this->client->request('GET', '/faktury?rodzaj=zakupowe');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'PET/1/2026');
        self::assertSelectorTextContains('tbody', 'PETROMEX Sp. z o.o.');
        self::assertSelectorExists('tbody tr.is-unread');
        self::assertSelectorTextContains('.tabs .tab-active', '1');

        self::assertSame('/faktury/'.$invoice->getId(), $notifications[0]->getLink(), 'Jedna nowa faktura – powiadomienie prowadzi wprost do niej');
        $this->client->request('GET', '/faktury/'.$invoice->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/powiadomienia/dzwonek');
        self::assertSelectorNotExists('.dot-new', 'Odczytanie faktury gasi jej powiadomienie');
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

        $this->fakeKsef()->add(Fa3Builder::invoice(self::FARM_NIP, 'Gospodarstwo testowe', '1131000001', 'Mleczarnia', 'FV/1/2026', new \DateTimeImmutable('2026-05-10'))
            ->item('Mleko surowe', 'l', 50000, 2.2, '5')->toXml(), new \DateTimeImmutable('2026-05-10 12:00'));
        $this->addInvoice($this->costInvoice('ENE/1/2026', '2026-05-05', $energyNip, 'Energa')->item('Energia', 'kWh', 5000, 0.7));

        $this->syncKsef();

        $income = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'FV/1/2026']);
        self::assertSame(InvoiceKind::Income, $income?->getKind());
        self::assertSame('1131000001', $income->getContractor()?->getNip());
        self::assertFalse($income->hasXml(), 'XML sprzedażowych pobiera się na żądanie');

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

    public function testBackfillInterruptedInLaterWindowResumesFromThatWindow(): void
    {
        $start = (new \DateTimeImmutable('-200 days'))->setTime(0, 0);
        $this->service(Settings::class)->set(SettingKey::KSEF_SYNC_FROM, $start->format('Y-m-d'));
        foreach (['A' => 10, 'B' => 120, 'C' => 190] as $suffix => $days) {
            $date = $start->modify(\sprintf('+%d days', $days));
            $this->fakeKsef()->add($this->costInvoice('PET/'.$suffix, $date->format('Y-m-d'))->item('ON', 'l', 100, 5)->toXml(), $date->setTime(12, 0));
        }

        // Pierwsze okno 90-dniowe przechodzi, drugie trafia na awarię KSeF.
        $secondWindow = $start->modify(\sprintf('+%d days', MetadataQuery::MAX_RANGE_DAYS));
        $this->fakeKsef()->failWhen(static fn (MetadataQuery $q): ?KsefException => $q->from >= $secondWindow ? new KsefException('KSeF jest chwilowo niedostępny.') : null);
        $this->syncKsef();

        self::assertSame(['PET/A'], $this->invoiceNumbers());
        self::assertTrue($this->lastSync()->isFailed());

        $this->fakeKsef()->failWhen(null);
        $this->syncKsef();

        self::assertSame(['PET/A', 'PET/B', 'PET/C'], $this->invoiceNumbers(), 'Kursor nie może przeskoczyć okien, których nie pobrano');
        self::assertFalse($this->lastSync()->isFailed());
    }

    public function testUnreadableInvoiceDoesNotBlockOthersAndIsRetried(): void
    {
        $broken = $this->fakeKsef()->add($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 100, 5)->toXml(), new \DateTimeImmutable('-2 days'));
        $this->fakeKsef()->add($this->costInvoice('PET/2/2026', '2026-05-05')->item('ON', 'l', 100, 5)->toXml(), new \DateTimeImmutable('-1 day'));
        $this->fakeKsef()->markUnreadable($broken);

        $this->syncKsef();
        $this->addInvoice($this->costInvoice('PET/3/2026', '2026-05-06')->item('ON', 'l', 100, 5));
        $this->syncKsef();

        self::assertSame(['PET/2/2026', 'PET/3/2026'], $this->invoiceNumbers(), 'Nieczytelna pozycja nie wstrzymuje kolejnych');
        self::assertStringContainsString($broken, (string) $this->lastSync()->getError());

        $this->fakeKsef()->markUnreadable($broken, false);
        $this->syncKsef();

        self::assertSame(['PET/1/2026', 'PET/2/2026', 'PET/3/2026'], $this->invoiceNumbers(), 'Odrzucona pozycja jest ponawiana, aż się uda');
        self::assertFalse($this->lastSync()->isFailed());
    }

    public function testSalesAreSyncedWhenPurchaseQueryFails(): void
    {
        $this->fakeKsef()->add(Fa3Builder::invoice(self::FARM_NIP, 'Gospodarstwo testowe', '1131000001', 'Mleczarnia', 'FV/1/2026', new \DateTimeImmutable('2026-05-10'))
            ->item('Mleko surowe', 'l', 50000, 2.2, '5')->toXml());
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 100, 5));
        $this->fakeKsef()->failWhen(static fn (MetadataQuery $q): ?KsefException => SubjectType::Buyer === $q->subjectType ? new KsefException('Brak uprawnień do faktur zakupowych.') : null);

        $this->syncKsef();

        self::assertSame(['FV/1/2026'], $this->invoiceNumbers());
        self::assertSame('Brak uprawnień do faktur zakupowych.', $this->lastSync()->getError());
        self::assertSame(1, $this->lastSync()->getNewCount());

        $this->fakeKsef()->failWhen(null);
        $this->syncKsef();

        self::assertSame(['FV/1/2026', 'PET/1/2026'], $this->invoiceNumbers());
        self::assertFalse($this->lastSync()->isFailed());
    }

    public function testSimulatorRefusesFarmWithProductionEnvironment(): void
    {
        $owner = UserFactory::owner();
        $this->service(Settings::class)->set(SettingKey::KSEF_ENVIRONMENT, 'prod');
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 100, 5));

        $this->syncKsef();

        self::assertSame([], $this->invoiceNumbers());
        self::assertStringContainsString('KSEF_CLIENT=fake', (string) $this->lastSync()->getError());
        self::assertSame([], $this->service(Settings::class)->get(SettingKey::KSEF_CURSORS), 'Symulator nie może przesunąć kursora produkcyjnego gospodarstwa');

        $this->loginAs($owner);
        $this->client->request('GET', '/ustawienia/ksef');
        self::assertSelectorTextContains('.alert-danger', 'KSEF_CLIENT=fake');
    }

    public function testClientModeMustBeHttpOrFake(): void
    {
        $settings = $this->service(Settings::class);
        $selector = fn (string $mode): KsefClientSelector => new KsefClientSelector($this->service(HttpKsefClient::class), $this->service(FakeKsefClient::class), $this->service(KsefIdentity::class), $mode);

        self::assertStringContainsString('„true”', (string) $selector('true')->configurationError());
        self::assertNull($selector('http')->configurationError());
        self::assertNull($selector('fake')->configurationError());

        $settings->set(SettingKey::KSEF_ENVIRONMENT, 'prod');
        self::assertNull($selector('http')->configurationError());
        self::assertNotNull($selector('fake')->configurationError());
    }

    public function testCursorFromOtherSourceIsDiscarded(): void
    {
        $settings = $this->service(Settings::class);
        $now = (new \DateTimeImmutable())->format(\DATE_ATOM);
        $this->fakeKsef()->add($this->costInvoice('PET/1/2026', '2026-05-04')->item('ON', 'l', 100, 5)->toXml(), new \DateTimeImmutable('-30 days'));

        // Kursor z tego samego źródła obowiązuje – starsza faktura jest już „za nim”.
        $settings->set(SettingKey::KSEF_CURSORS, ['source' => $this->service(KsefClientSelector::class)->source(), 'Subject1' => $now, 'Subject2' => $now]);
        $this->syncKsef();
        self::assertSame([], $this->invoiceNumbers());

        // Kursor bez źródła (zapisany przed tą zmianą) albo z innego źródła – pobieramy od daty początkowej.
        $settings->set(SettingKey::KSEF_CURSORS, ['Subject1' => $now, 'Subject2' => $now]);
        $this->syncKsef();
        self::assertSame(['PET/1/2026'], $this->invoiceNumbers());
        self::assertSame($this->service(KsefClientSelector::class)->source(), $settings->get(SettingKey::KSEF_CURSORS)['source'] ?? null);
    }

    public function testErrorIsRecordedAndNotified(): void
    {
        $farm = FarmFactory::createOne(['nip' => null]);
        $owner = UserFactory::owner($farm);
        $this->useFarm($farm);

        $this->syncKsef();

        $sync = $this->em->getRepository(KsefSync::class)->findOneBy([]);
        self::assertTrue($sync?->isFailed());
        self::assertSame(1, $this->em->getRepository(Notification::class)->count(['user' => $owner, 'type' => NotificationType::KsefError]));
    }

    /**
     * @return list<string>
     */
    private function invoiceNumbers(): array
    {
        return array_values(array_map(static fn (Invoice $i): string => $i->getNumber(), $this->em->getRepository(Invoice::class)->findBy([], ['number' => 'ASC'])));
    }

    private function lastSync(): KsefSync
    {
        $sync = $this->em->getRepository(KsefSync::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($sync);

        return $sync;
    }
}
