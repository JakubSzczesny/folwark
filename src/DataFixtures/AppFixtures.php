<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Contract;
use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\Invoice;
use App\Entity\InvoiceRule;
use App\Entity\ItemMapping;
use App\Entity\Machine;
use App\Entity\MachineCategory;
use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use App\Entity\ServiceType;
use App\Entity\Unit;
use App\Entity\User;
use App\Enum\ContractKind;
use App\Enum\InvoiceKind;
use App\Enum\MeterUnit;
use App\Enum\PaymentFrequency;
use App\Enum\UserRole;
use App\Farm\FarmCreator;
use App\Farm\FarmRunner;
use App\Invoice\InvoiceXmlService;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\KsefSynchronizer;
use App\Machine\MachineInfo;
use App\Machine\MachineManager;
use App\Machine\ServiceRecorder;
use App\Order\OrderPlanner;
use App\Product\ItemAssigner;
use App\Product\ProductMatcher;
use App\Repository\ContractorRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dane demonstracyjne do pracy lokalnej. Hasło wszystkich kont: "folwark-demo".
 * Faktury powstają w symulatorze KSeF i trafiają do bazy przez zwykłą synchronizację.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'folwark-demo';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly FarmCreator $farmCreator,
        private readonly FarmRunner $runner,
        private readonly Settings $settings,
        private readonly DemoInvoiceGenerator $generator,
        private readonly FakeKsefClient $fakeKsef,
        private readonly KsefSynchronizer $synchronizer,
        private readonly InvoiceXmlService $xml,
        private readonly ContractorRepository $contractors,
        private readonly MachineManager $machines,
        private readonly MachineInfo $machineInfo,
        private readonly ServiceRecorder $serviceRecorder,
        private readonly ItemAssigner $assigner,
        private readonly OrderPlanner $planner,
        #[Autowire('%env(resolve:KSEF_FAKE_DIR)%')]
        private readonly string $fakeDirectory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        foreach ([
            'owner' => ['wlasciciel@folwark.test', 'Jan Kowalski'],
            'worker' => ['pracownik@folwark.test', 'Piotr Nowak'],
            'accountant' => ['ksiegowa@folwark.test', 'Anna Wiśniewska'],
        ] as $key => [$email, $name]) {
            $user = (new User())->setEmail($email)->setFullName($name);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
            $users[$key] = $user;
        }
        $manager->flush();

        $farm = $this->farmCreator->create(DemoInvoiceGenerator::FARM_NAME, DemoInvoiceGenerator::FARM_NIP, $users['owner']);
        $manager->persist(new FarmMembership($farm, $users['worker'], UserRole::Worker));
        $manager->persist(new FarmMembership($farm, $users['accountant'], UserRole::Accountant));

        $meadows = $this->farmCreator->create('Gospodarstwo Łąkowe Nowakowie', '5211000005', $users['owner']);
        $manager->persist(new FarmMembership($meadows, $users['worker'], UserRole::Owner));
        $manager->flush();

        $this->runner->within($farm, fn (Farm $farm) => $this->loadMainFarm($manager, $users['owner']));
    }

    private function loadMainFarm(ObjectManager $manager, User $owner): void
    {
        $this->settings->set(SettingKey::KSEF_SYNC_FROM, '2025-04-01', false);

        $energy = $manager->getRepository(CostCategory::class)->findOneBy(['name' => 'Energia']);
        $insurance = $manager->getRepository(CostCategory::class)->findOneBy(['name' => 'Ubezpieczenia']);
        $general = $manager->getRepository(CostCenter::class)->findOneBy(['name' => 'Ogólne gospodarstwa']);
        foreach ([['energia', $energy], ['ubezpieczenia', $insurance]] as [$partner, $category]) {
            $manager->persist((new InvoiceRule())
                ->setContractorNip(DemoInvoiceGenerator::PARTNERS[$partner]['nip'])
                ->setCostCenter($general)
                ->setCostCategory($category)
                ->setNote('Dane demonstracyjne'));
        }
        $manager->flush();

        $this->generator->generate($this->fakeDirectory, new \DateTimeImmutable('2025-04-01'), new \DateTimeImmutable('today'));
        $this->fakeKsef->reset();
        $this->synchronizer->sync(true, $owner);

        foreach ($manager->getRepository(Invoice::class)->findAll() as $invoice) {
            if (!$invoice->hasXml() && ($invoice->isCost() || $invoice->getIssueDate() > new \DateTimeImmutable('-60 days'))) {
                $this->xml->fetch($invoice);
            }
        }
        $manager->flush();

        $tons = $manager->getRepository(Unit::class)->findOneBy(['symbol' => 't']);
        $elevator = $this->contractors->findOneBy(['nip' => DemoInvoiceGenerator::PARTNERS['elewator']['nip']]);
        if (null !== $elevator) {
            $contract = (new Contract())
                ->setContractor($elevator)
                ->setNumber('UK/14/2026')
                ->setSubject('Sprzedaż pszenicy konsumpcyjnej ze zbiorów 2026')
                ->setKind(ContractKind::Sale)
                ->setConcludedAt(new \DateTimeImmutable('2026-05-20'))
                ->setValidFrom(new \DateTimeImmutable('2026-07-15'))
                ->setValidTo(new \DateTimeImmutable('+25 days'))
                ->setContractedQuantity('200')
                ->setQuantityUnit($tons)
                ->setPrice('865')
                ->setPriceUnit($tons)
                ->setQualityTerms("Wilgotność do 14,5 %\nZanieczyszczenia do 2 %\nLiczba opadania min. 250 s");
            $manager->persist($contract);
            foreach ($manager->getRepository(Invoice::class)->findBy(['contractor' => $elevator]) as $invoice) {
                if ($invoice->getIssueDate() >= new \DateTimeImmutable('2026-07-15')) {
                    $invoice->setContract($contract);
                }
            }
        }

        $dairy = $this->contractors->findOneBy(['nip' => DemoInvoiceGenerator::PARTNERS['mleczarnia']['nip']]);
        if (null !== $dairy) {
            $manager->persist((new Contract())
                ->setContractor($dairy)
                ->setNumber('DZ/3/2024')
                ->setSubject('Dzierżawa 12 ha łąk przy rzece (dz. 114/2, 114/3)')
                ->setKind(ContractKind::Lease)
                ->setConcludedAt(new \DateTimeImmutable('2024-03-01'))
                ->setValidFrom(new \DateTimeImmutable('2024-04-01'))
                ->setValidTo(new \DateTimeImmutable('2029-03-31'))
                ->setRentAmount('3600.00')
                ->setPaymentFrequency(PaymentFrequency::Quarterly)
                ->setFirstPaymentDate(new \DateTimeImmutable('2024-04-20')));
        }

        $manager->flush();
        $products = $this->loadProducts($manager);
        $this->loadMachines($manager, $owner, $products);
    }

    /**
     * Kartoteka towarów i zapamiętane przypisania pozycji z faktur pokazowych; faktury przypisują się
     * same, jak przy synchronizacji, a zamówienia przeliczają się z ich historii.
     *
     * @return array<string, Product>
     */
    private function loadProducts(ObjectManager $manager): array
    {
        $unit = static fn (string $symbol): ?Unit => $manager->getRepository(Unit::class)->findOneBy(['symbol' => $symbol]);
        $category = static fn (string $name): ?ProductCategory => $manager->getRepository(ProductCategory::class)->findOneBy(['name' => $name]);

        $catalog = [
            'on' => ['Olej napędowy', 'Paliwa', 'l', null, 2],
            'mleko18' => ['Mieszanka paszowa MLEKO 18%', 'Pasze treściwe i mieszanki', 't', null, 3],
            'bovimin' => ['Dodatek mineralny Bovi-Min', 'Dodatki mineralne', 'kg', null, 5],
            'saletra' => ['Saletra amonowa 34%', 'Nawozy', 't', null, 7],
            'polifoska' => ['Polifoska 6', 'Nawozy', 't', null, 7],
            'donovan' => ['Pszenica ozima KWS Donovan C1', 'Nasiona', 't', null, 14],
            'filtr-oleju' => ['Filtr oleju silnika JD', 'Filtry i części', 'szt', 'RE504836', 3],
            'filtr-paliwa' => ['Filtr paliwa JD', 'Filtry i części', 'szt', 'RE522878', 3],
            'olej-silnikowy' => ['Olej silnikowy 15W40', 'Oleje i smary', 'l', null, 3],
            'synulox' => ['Synulox LC tubostrzykawka', 'Leki weterynaryjne', 'szt', null, 1],
        ];
        $products = [];
        foreach ($catalog as $key => [$name, $categoryName, $symbol, $catalogNumber, $leadTime]) {
            $products[$key] = (new Product())->setName($name)->setCategory($category($categoryName))->setBaseUnit($unit($symbol))->setCatalogNumber($catalogNumber)->setLeadTimeDays($leadTime);
            $manager->persist($products[$key]);
        }

        $mappings = [
            'petromex' => ['Olej napędowy ON' => 'on'],
            'pasze' => ['Mieszanka paszowa MLEKO 18% luzem' => 'mleko18', 'Transport' => null, 'Dodatek mineralny Bovi-Min worek 25kg' => 'bovimin'],
            'nawozy' => ['Saletra amonowa 34% big bag 500kg' => 'saletra', 'Polifoska 6 big bag 500kg' => 'polifoska'],
            'nasiona' => ['Pszenica ozima KWS Donovan C1 worek 500kg' => 'donovan'],
            'serwis' => ['Filtr oleju silnika JD RE504836' => 'filtr-oleju', 'Olej silnikowy 15W40 5L' => 'olej-silnikowy', 'Filtr paliwa RE522878' => 'filtr-paliwa', 'Robocizna serwisowa' => null],
            'weterynarz' => ['Wizyta lekarsko-weterynaryjna' => null, 'Lek Synulox LC tubostrzykawka' => 'synulox'],
        ];
        foreach ($mappings as $partner => $items) {
            $contractor = $this->contractors->findOneBy(['nip' => DemoInvoiceGenerator::PARTNERS[$partner]['nip']]);
            foreach ($items as $itemName => $productKey) {
                $mapping = new ItemMapping($contractor, ProductMatcher::key($itemName));
                $mapping->remember(null !== $productKey ? $products[$productKey] : null, null === $productKey, null, null);
                $manager->persist($mapping);
            }
        }

        $shops = $manager->getRepository(ProductLinkCategory::class)->findOneBy(['name' => 'Sklepy']);
        if (null !== $shops) {
            $manager->persist((new ProductLink($products['filtr-oleju'], $shops))->setUrl('https://www.deere.pl')->setDescription('Części oryginalne John Deere'));
        }
        $manager->flush();

        foreach ($manager->getRepository(Invoice::class)->findAll() as $invoice) {
            $this->assigner->autoAssign($invoice);
        }
        $manager->flush();
        $this->planner->recalculate();

        return $products;
    }

    /**
     * @param array<string, Product> $products
     */
    private function loadMachines(ObjectManager $manager, User $owner, array $products): void
    {
        $categories = $manager->getRepository(MachineCategory::class);
        $machine = function (string $name, string $category, array $data) use ($owner, $categories): Machine {
            $machine = (new Machine())
                ->setName($name)
                ->setCategory($categories->findOneBy(['name' => $category]))
                ->setRegistrationNumber($data['reg'] ?? null)
                ->setBrand($data['brand'] ?? null)
                ->setModel($data['model'] ?? null)
                ->setProductionYear($data['year'] ?? null)
                ->setInsuranceUntil(isset($data['insurance']) ? new \DateTimeImmutable($data['insurance']) : null)
                ->setInspectionUntil(isset($data['inspection']) ? new \DateTimeImmutable($data['inspection']) : null)
                ->setMeterUnit($data['unit'] ?? null)
                ->setMeterValue($data['meter'] ?? null)
                ->setPayloadKg($data['payload'] ?? null);
            $this->machines->save($machine, $data['tare'] ?? null, null, false, $owner);

            return $machine;
        };

        $deere = $machine('John Deere 6155M', 'Ciągniki', ['reg' => 'CCH 45KL', 'brand' => 'John Deere', 'model' => '6155M', 'year' => 2019, 'insurance' => 'today +14 days', 'inspection' => 'today +120 days', 'unit' => MeterUnit::Hours, 'meter' => '6420.5', 'tare' => 6850]);
        $machine('Ursus C-360', 'Ciągniki', ['reg' => 'CCH 1234', 'brand' => 'Ursus', 'model' => 'C-360', 'year' => 1984, 'insurance' => 'today +200 days', 'inspection' => 'today -5 days', 'unit' => MeterUnit::Hours, 'meter' => '11230', 'tare' => 2050]);
        $trailer = $machine('Pronar T680', 'Przyczepy i naczepy', ['reg' => 'CCH 7U21', 'brand' => 'Pronar', 'model' => 'T680', 'year' => 2017, 'insurance' => 'today +150 days', 'inspection' => 'today +60 days', 'tare' => 3900, 'payload' => 14000]);
        $machine('John Deere + Pronar', 'Zestawy', [])->addComponent($deere)->addComponent($trailer);
        $machine('Claas Lexion 760', 'Kombajny', ['brand' => 'Claas', 'model' => 'Lexion 760', 'year' => 2015, 'insurance' => 'today +300 days', 'unit' => MeterUnit::Hours, 'meter' => '2310']);
        $machine('Ford Ranger', 'Samochody', ['reg' => 'CCH 9P88A', 'brand' => 'Ford', 'model' => 'Ranger', 'year' => 2021, 'insurance' => 'today +25 days', 'inspection' => 'today +40 days', 'unit' => MeterUnit::Kilometers, 'meter' => '184500']);
        $manager->flush();

        $infoCategories = $manager->getRepository(MachineInfoCategory::class);
        $filters = $infoCategories->findOneBy(['name' => 'Filtry', 'parent' => null]);
        foreach ([
            [['Oleju silnika', $filters], 'Filtr oleju silnika', 'RE504836', 'John Deere', '1 szt.', 'John Deere – części https://www.deere.pl', 'filtr-oleju'],
            [['Paliwa', $filters], 'Filtr paliwa', 'RE522878', 'John Deere', '1 szt.', '', 'filtr-paliwa'],
            [['Oleje i płyny', null], 'Olej silnikowy 15W40', 'Plus-50 II', 'John Deere', '19 l', '', 'olej-silnikowy'],
            [['Opony i koła', null], 'Opony tylne', null, 'Michelin', '710/70 R42', '', null],
        ] as [[$categoryName, $parent], $name, $catalog, $maker, $size, $links, $productKey]) {
            $infoCategory = $infoCategories->findOneBy(['name' => $categoryName, 'parent' => $parent]);
            if (null === $infoCategory) {
                continue;
            }
            $entry = (new MachineInfoEntry($deere, $infoCategory))->setName($name)->setCatalogNumber($catalog)->setManufacturer($maker)->setSizeQuantity($size)->setProduct($products[$productKey] ?? null);
            $this->machineInfo->saveEntry($entry, [], $links);
        }

        $workshop = $this->contractors->findOneBy(['nip' => DemoInvoiceGenerator::PARTNERS['serwis']['nip']]);
        if (null === $workshop) {
            return;
        }
        $oilChange = $manager->getRepository(ServiceType::class)->findOneBy(['name' => 'Wymiana oleju']);
        $invoices = array_reverse($manager->getRepository(Invoice::class)->findBy(['contractor' => $workshop, 'kind' => InvoiceKind::Cost], ['issueDate' => 'DESC'], 3));
        $meter = 6420.5 - 250 * \count($invoices);
        foreach ($invoices as $i => $invoice) {
            $meter += 250;
            $service = $this->serviceRecorder->prepare($deere, $owner)
                ->setDate($invoice->getIssueDate())
                ->setMeterValue(number_format($meter - 30, 1, '.', ''))
                ->setType($oilChange)
                ->setContractor($workshop)
                ->setDescription('Wymiana oleju silnikowego i filtrów')
                ->setInvoice($invoice);
            if ($i === \count($invoices) - 1) {
                $service->setNextServiceDate(new \DateTimeImmutable('today +10 days'))->setNextServiceMeter(number_format($meter + 220, 1, '.', ''));
            }
            $this->serviceRecorder->save($service);
        }
    }
}
