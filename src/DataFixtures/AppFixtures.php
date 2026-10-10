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
use App\Entity\Unit;
use App\Entity\User;
use App\Enum\ContractKind;
use App\Enum\PaymentFrequency;
use App\Enum\UserRole;
use App\Farm\FarmCreator;
use App\Farm\FarmRunner;
use App\Invoice\InvoiceXmlService;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\KsefSynchronizer;
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
    }
}
