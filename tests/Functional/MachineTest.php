<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Machine;
use App\Entity\MachineInfoAttachment;
use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Entity\MachineService;
use App\Entity\Notification;
use App\Entity\ServiceType;
use App\Enum\CostCenterType;
use App\Enum\MeterUnit;
use App\Enum\NotificationType;
use App\Machine\MachineCosts;
use App\Machine\MachineInfo;
use App\Machine\MachineManager;
use App\Notification\DeadlineNotifications;
use App\Repository\MachineServiceRepository;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\FarmFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\MachineFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MachineTest extends FunctionalTestCase
{
    public function testOwnerAddsMachineAndInsuranceShowsOnDashboard(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/maszyny/nowa');
        $this->client->submitForm('Zapisz', [
            'machine[name]' => 'John Deere 6155M',
            'machine[registrationNumber]' => 'cch 45kl',
            'machine[tareKg]' => '6850',
            'machine[insuranceUntil]' => (new \DateTimeImmutable('today +14 days'))->format('Y-m-d'),
            'machine[meterValue]' => '6420.5',
            'machine[meterUnit]' => MeterUnit::Hours->value,
        ]);
        self::assertResponseRedirects();

        $machine = $this->service(EntityManagerInterface::class)->getRepository(Machine::class)->findOneBy(['name' => 'John Deere 6155M']);
        self::assertNotNull($machine);
        self::assertSame('CCH 45KL', $machine->getRegistrationNumber());
        self::assertSame(CostCenterType::Machine, $machine->getCostCenter()?->getType());
        self::assertSame('John Deere 6155M (CCH 45KL)', $machine->getCostCenter()->getName());
        self::assertCount(1, $machine->getTareHistory());

        $this->client->request('GET', '/maszyny');
        self::assertSelectorTextContains('tbody', 'John Deere 6155M');

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('main', 'Ubezpieczenie');
        self::assertSelectorTextContains('main', 'John Deere 6155M (CCH 45KL)');
        self::assertSelectorTextContains('main', 'za 14 dni');
    }

    public function testWorkerSeesMachinesButCannotManageThemOrCosts(): void
    {
        $machine = MachineFactory::createOne(['name' => 'Ursus C-360']);
        $this->loginAs(UserFactory::worker());

        $this->client->request('GET', '/maszyny/'.$machine->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ursus C-360');
        self::assertSelectorTextNotContains('.tabs', 'Koszty');
        self::assertSelectorExists('a[href$="/serwis/nowy"]');

        $this->client->request('GET', '/maszyny/nowa');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/maszyny/'.$machine->getId().'/koszty');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubcategoryAndEntryWithPhotoAndLink(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $this->useFarm();
        $em->persist($filters = new MachineInfoCategory('Filtry'));
        $em->flush();
        $machine = MachineFactory::createOne();
        $this->loginAs(UserFactory::worker());

        $this->client->request('GET', \sprintf('/maszyny/%d/informacje/kategoria?rodzic=%d', $machine->getId(), $filters->getId()));
        $this->client->submitForm('Dodaj', ['name' => 'Oleju silnika']);
        $subcategory = $em->getRepository(MachineInfoCategory::class)->findOneBy(['name' => 'Oleju silnika']);
        self::assertNotNull($subcategory);
        self::assertSame($filters->getId(), $subcategory->getParent()?->getId());
        self::assertResponseRedirects(\sprintf('/maszyny/%d?kategoria=%d', $machine->getId(), $subcategory->getId()));

        $crawler = $this->client->request('GET', \sprintf('/maszyny/%d/informacje/wpis?kategoria=%d', $machine->getId(), $subcategory->getId()));
        $form = $crawler->selectButton('Zapisz')->form([
            'machine_info_entry[name]' => 'Filtr oleju silnika',
            'machine_info_entry[catalogNumber]' => 'RE504836',
            'machine_info_entry[links]' => 'Sklep Agrihandel https://sklep.example.pl/re504836',
        ]);
        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), ['machine_info_entry' => ['files' => [self::photo()]]]);
        self::assertResponseRedirects(\sprintf('/maszyny/%d?kategoria=%d', $machine->getId(), $subcategory->getId()));

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Filtr oleju silnika');
        self::assertSelectorTextContains('main', 'nr RE504836');
        self::assertSelectorExists('a[href="https://sklep.example.pl/re504836"]');
        $thumbnail = $crawler->filter('img[src*="/informacje/zalacznik/"]')->attr('src');
        self::assertNotNull($thumbnail);

        $this->client->request('GET', $thumbnail);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');

        $photo = $em->getRepository(MachineInfoAttachment::class)->findOneBy(['description' => 'zdjecie.jpg']);
        self::assertNotNull($photo);
        $this->loginAs(UserFactory::owner($other = FarmFactory::createOne()), $other);
        $this->client->request('GET', '/maszyny/informacje/zalacznik/'.$photo->getId());
        self::assertResponseStatusCodeSame(404, 'Zdjęcie z innego gospodarstwa jest niedostępne');
    }

    public function testCopyInfoFromAnotherMachineSkipsExistingEntries(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $this->useFarm();
        $em->persist($filters = new MachineInfoCategory('Filtry'));
        $em->persist($oil = new MachineInfoCategory('Oleju silnika', $filters));
        $em->persist($tyres = new MachineInfoCategory('Opony'));
        $em->flush();
        $source = MachineFactory::createOne();
        $target = MachineFactory::createOne();

        $info = $this->service(MachineInfo::class);
        $info->saveEntry((new MachineInfoEntry($source, $oil))->setName('Filtr oleju')->setCatalogNumber('RE504836'), [], 'https://www.deere.pl');
        $info->saveEntry((new MachineInfoEntry($source, $filters))->setName('Filtr powietrza'));
        $info->saveEntry((new MachineInfoEntry($source, $tyres))->setName('Opony tylne'));
        $info->saveEntry((new MachineInfoEntry($target, $oil))->setName('Filtr oleju')->setCatalogNumber('RE504836'));

        self::assertSame(1, $info->copy($source, $target, [$filters]), 'Filtr oleju już jest, opony nie były wybrane');

        $names = array_map(static fn (MachineInfoEntry $e): string => $e->getName(), $em->getRepository(MachineInfoEntry::class)->findBy(['machine' => $target]));
        self::assertEqualsCanonicalizing(['Filtr oleju', 'Filtr powietrza'], $names);
    }

    public function testServiceEntryUpdatesMeterLinksInvoiceAndPlansNextService(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $this->useFarm();
        $em->persist($oilChange = new ServiceType('Wymiana oleju'));
        $em->flush();
        $workshop = ContractorFactory::createOne(['name' => 'AGRO-SERWIS', 'nip' => '8511000005']);
        $other = ContractorFactory::createOne(['name' => 'Inny warsztat', 'nip' => '7311000000']);
        $near = InvoiceFactory::createOne(['contractor' => $workshop, 'number' => 'FV/BLISKO', 'issueDate' => new \DateTimeImmutable('today -5 days')]);
        InvoiceFactory::createOne(['contractor' => $workshop, 'number' => 'FV/DALEKO', 'issueDate' => new \DateTimeImmutable('today -40 days')]);
        InvoiceFactory::createOne(['contractor' => $other, 'number' => 'FV/INNY', 'issueDate' => new \DateTimeImmutable('today -2 days')]);
        $machine = MachineFactory::createOne(['meterValue' => '1000.0', 'meterUnit' => MeterUnit::Hours]);
        $owner = UserFactory::owner();
        $this->loginAs($owner);

        $date = (new \DateTimeImmutable('today -2 days'))->format('Y-m-d');
        $this->client->request('GET', \sprintf('/maszyny/serwis/faktury?kontrahent=%d&data=%s', $workshop->getId(), $date));
        self::assertSelectorTextContains('body', 'FV/BLISKO');
        self::assertSelectorTextNotContains('body', 'FV/DALEKO');
        self::assertSelectorTextNotContains('body', 'FV/INNY');

        $crawler = $this->client->request('GET', \sprintf('/maszyny/%d/serwis/nowy', $machine->getId()));
        $form = $crawler->selectButton('Zapisz wpis')->form();
        $values = $form->getPhpValues();
        $values['machine_service'] = [
            'date' => $date,
            'meterValue' => '1250',
            'type' => (string) $oilChange->getId(),
            'contractor' => (string) $workshop->getId(),
            'description' => 'Wymiana oleju i filtrów',
            'invoice' => (string) $near->getId(),
            'nextServiceDate' => (new \DateTimeImmutable('today +10 days'))->format('Y-m-d'),
            'parts' => [['name' => 'Filtr oleju RE504836', 'quantity' => '1']],
            '_token' => $values['machine_service']['_token'],
        ];
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects(\sprintf('/maszyny/%d/serwis', $machine->getId()));

        $em->clear();
        $machine = $em->find(Machine::class, $machine->getId());
        self::assertEquals(1250.0, (float) $machine?->getMeterValue(), 'Wyższy licznik z serwisu trafia do maszyny');
        $service = $em->getRepository(MachineService::class)->findOneBy(['machine' => $machine]);
        self::assertSame('FV/BLISKO', $service?->getInvoice()?->getNumber());
        self::assertCount(1, $service->getParts());

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('main', 'wymiana oleju');

        $this->useFarm();
        $em = $this->service(EntityManagerInterface::class);
        $machine = $em->find(Machine::class, $machine?->getId()) ?? throw new \LogicException();
        $notifications = $this->service(DeadlineNotifications::class);
        $notifications->generate();
        $notifications->generate();
        self::assertSame(1, $em->getRepository(Notification::class)->count(['type' => NotificationType::MachineService, 'user' => $owner->getId()]));

        $em->persist((new MachineService($machine))->setDate(new \DateTimeImmutable('today'))->setType($em->find(ServiceType::class, $oilChange->getId())));
        $em->flush();
        self::assertSame([], $this->service(MachineServiceRepository::class)->findOpenPlans($machine), 'Kolejna wymiana oleju zamyka poprzedni plan');
    }

    public function testInsuranceRemindersFollowThresholds(): void
    {
        $owner = UserFactory::owner();
        $machine = MachineFactory::createOne(['insuranceUntil' => new \DateTimeImmutable('today +20 days')]);
        $this->useFarm();
        $notifications = $this->service(DeadlineNotifications::class);
        $repository = $this->service(EntityManagerInterface::class)->getRepository(Notification::class);

        $notifications->generate();
        $notifications->generate();
        self::assertSame(1, $repository->count(['type' => NotificationType::MachineInsurance, 'user' => $owner->getId()]));

        $notifications->generate(new \DateTimeImmutable('today +8 days'));
        self::assertSame(2, $repository->count(['type' => NotificationType::MachineInsurance, 'user' => $owner->getId()]), 'Próg 14 dni to kolejne przypomnienie');
        self::assertNotNull($machine->getId());
    }

    public function testCostsCountEachInvoiceOnce(): void
    {
        $this->useFarm();
        $machine = new Machine();
        $machine->setName('Claas Lexion 760');
        $this->service(MachineManager::class)->save($machine, null, null, false, null);
        $em = $this->service(EntityManagerInterface::class);

        $assigned = InvoiceFactory::createOne(['issueDate' => new \DateTimeImmutable('2026-05-10'), 'costCenter' => $machine->getCostCenter()]);
        $assigned->setAmounts('1000.00', '230.00', '1230.00');
        $linked = InvoiceFactory::createOne(['issueDate' => new \DateTimeImmutable('2026-06-01')]);
        $linked->setAmounts('500.00', '115.00', '615.00');
        $em->flush();
        $machine = $em->find(Machine::class, $machine->getId()) ?? throw new \LogicException();
        $em->persist((new MachineService($machine))->setDate(new \DateTimeImmutable('2026-05-10'))->setInvoice($em->find($assigned::class, $assigned->getId())));
        $em->persist((new MachineService($machine))->setDate(new \DateTimeImmutable('2026-06-01'))->setInvoice($em->find($linked::class, $linked->getId())));
        $em->persist((new MachineService($machine))->setDate(new \DateTimeImmutable('2025-08-01'))->setNetCost('200.00'));
        $em->flush();

        $rows = $this->service(MachineCosts::class)->rows($machine);
        self::assertCount(3, $rows);
        self::assertEquals([2026 => 1500.0, 2025 => 200.0], MachineCosts::yearlyTotals($rows));

        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', \sprintf('/maszyny/%d/koszty?rok=2026', $machine->getId()));
        self::assertSelectorTextContains('tfoot', "1\u{00A0}500,00");
    }

    private static function photo(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'folwark').'.jpg';
        $image = imagecreatetruecolor(320, 200) ?: throw new \RuntimeException('GD');
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 46, 125, 70));
        imagejpeg($image, $path);

        return new UploadedFile($path, 'zdjecie.jpg', 'image/jpeg', null, true);
    }
}
