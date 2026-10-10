<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Contract;
use App\Entity\CostCategory;
use App\Entity\CostCenter;
use App\Entity\Invoice;
use App\Entity\InvoiceRule;
use App\Entity\Unit;
use App\Entity\User;
use App\Enum\ContractKind;
use App\Enum\UserRole;
use App\Invoice\InvoiceXmlService;
use App\Ksef\Fake\FakeKsefClient;
use App\Ksef\KsefSynchronizer;
use App\Repository\ContractorRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Setup\StartupData;
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
        private readonly StartupData $startupData,
        private readonly UserPasswordHasherInterface $hasher,
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
        $this->startupData->seed();
        $owner = null;

        foreach ([
            ['wlasciciel@folwark.test', 'Jan Kowalski', UserRole::Owner],
            ['pracownik@folwark.test', 'Piotr Nowak', UserRole::Worker],
            ['ksiegowa@folwark.test', 'Anna Wiśniewska', UserRole::Accountant],
        ] as [$email, $name, $role]) {
            $user = (new User())->setEmail($email)->setFullName($name)->setRole($role);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
            $owner ??= $user;
        }

        $this->settings->set(SettingKey::FARM_NAME, DemoInvoiceGenerator::FARM_NAME, false);
        $this->settings->set(SettingKey::FARM_NIP, DemoInvoiceGenerator::FARM_NIP, false);
        $this->settings->set(SettingKey::KSEF_SYNC_FROM, '2025-04-01', false);
        $manager->flush();

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
        $this->synchronizer->sync(true, $owner instanceof User ? $owner : null);

        foreach ($manager->getRepository(Invoice::class)->findAll() as $invoice) {
            if (!$invoice->hasXml() && ($invoice->isCost() || $invoice->getIssueDate() > new \DateTimeImmutable('-60 days'))) {
                $this->xml->fetch($invoice);
            }
        }
        $manager->flush();

        $elevator = $this->contractors->findOneBy(['nip' => DemoInvoiceGenerator::PARTNERS['elewator']['nip']]);
        $tons = $manager->getRepository(Unit::class)->findOneBy(['symbol' => 't']);
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

        $manager->flush();
    }
}
