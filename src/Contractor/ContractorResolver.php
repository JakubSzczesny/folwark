<?php

declare(strict_types=1);

namespace App\Contractor;

use App\Entity\Contractor;
use App\Entity\ContractorBankAccount;
use App\Entity\Invoice;
use App\Enum\ContractorKind;
use App\Enum\DataSource;
use App\Ksef\Xml\ParsedParty;
use App\Repository\ContractorRepository;
use App\Util\Nip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Identyfikacja kontrahenta po NIP przy każdej fakturze i aktualizacja jego danych z najnowszej faktury.
 */
class ContractorResolver implements ResetInterface
{
    /** @var array<string, Contractor> kontrahenci utworzeni w tej samej synchronizacji, jeszcze bez flush */
    private array $pending = [];

    public function __construct(
        private readonly ContractorRepository $contractors,
        private readonly ContractorDataUpdater $updater,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function resolve(Invoice $invoice, ?string $rawNip, ?string $name): ?Contractor
    {
        $nip = Nip::normalize($rawNip);
        if (null === $nip) {
            return null;
        }

        $contractor = $this->pending[$nip] ?? $this->contractors->findOneBy(['nip' => $nip]);
        if (null === $contractor) {
            $contractor = (new Contractor())->setNip($nip)->setName($name ?? $nip)->setDataSource(DataSource::Ksef);
            $contractor->setDataFromInvoice($invoice)->touchData();
            $this->em->persist($contractor);
            $this->pending[$nip] = $contractor;
        } elseif ($this->updater->acceptsInvoiceData($contractor, $invoice)) {
            $this->persistHistory($this->updater->apply($contractor, ['name' => $name], DataSource::Ksef, $invoice));
            $contractor->setDataFromInvoice($invoice);
        }

        $kind = $contractor->getKind();
        $contractor->setKind(null === $kind ? ($invoice->isCost() ? ContractorKind::Supplier : ContractorKind::Customer) : $kind->with($invoice->getKind()));

        return $contractor;
    }

    /**
     * Adres i rachunki z pełnego XML faktury.
     *
     * @param list<string> $bankAccounts
     */
    public function applyXml(Contractor $contractor, Invoice $invoice, ParsedParty $party, bool $isSeller, array $bankAccounts): void
    {
        if ($this->updater->acceptsInvoiceData($contractor, $invoice)) {
            $address = AddressParser::parse($party->addressLine1, $party->addressLine2);
            $this->persistHistory($this->updater->apply($contractor, [
                'name' => $party->name,
                'street' => $address['street'],
                'postalCode' => $address['postalCode'],
                'city' => $address['city'],
                'country' => $party->countryCode,
                'email' => $party->email,
                'phone' => $party->phone,
            ], DataSource::KsefXml, $invoice));
            $contractor->setDataFromInvoice($invoice);
        }

        if ($isSeller) {
            foreach ($bankAccounts as $number) {
                if (null === $contractor->findBankAccount($number)) {
                    $contractor->addBankAccount(new ContractorBankAccount($contractor, $number, DataSource::KsefXml));
                }
            }
        }
    }

    public function reset(): void
    {
        $this->pending = [];
    }

    /**
     * @param list<\App\Entity\ContractorHistory> $entries
     */
    private function persistHistory(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->em->persist($entry);
        }
    }
}
