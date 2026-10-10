<?php

declare(strict_types=1);

namespace App\Contractor;

use App\Entity\Contractor;
use App\Entity\ContractorBankAccount;
use App\Enum\DataSource;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\WhiteList\WhiteListClient;
use App\WhiteList\WhiteListException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Uzupełnia brakującą nazwę i adres kontrahenta z Białej listy VAT oraz oznacza jego rachunki.
 */
class WhiteListEnricher
{
    private const string RECHECK_AFTER = '-30 days';

    public function __construct(
        private readonly WhiteListClient $client,
        private readonly Settings $settings,
        private readonly ContractorDataUpdater $updater,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->bool(SettingKey::WHITE_LIST_ENABLED);
    }

    public function needsEnrichment(Contractor $contractor): bool
    {
        if (!$this->isEnabled() || null === $contractor->getNip() || $contractor->isUpdateLocked()) {
            return false;
        }

        $checkedAt = $contractor->getWhiteListCheckedAt();
        if (null !== $checkedAt && $checkedAt > new \DateTimeImmutable(self::RECHECK_AFTER)) {
            return false;
        }

        return $contractor->getName() === $contractor->getNip() || !$contractor->hasAddress();
    }

    /**
     * @return bool czy podmiot znaleziono w wykazie
     *
     * @throws WhiteListException
     */
    public function enrich(Contractor $contractor): bool
    {
        $nip = $contractor->getNip();
        if (null === $nip) {
            return false;
        }

        $subject = $this->client->findByNip($nip, new \DateTimeImmutable('today'));
        $contractor->setWhiteListCheckedAt(new \DateTimeImmutable());

        if (null === $subject) {
            foreach ($contractor->getBankAccounts() as $account) {
                $account->markWhiteList(false);
            }

            return false;
        }

        if (!$contractor->isUpdateLocked()) {
            $values = [];
            if ($contractor->getName() === $nip || '' === $contractor->getName()) {
                $values['name'] = $subject->name;
            }
            if (!$contractor->hasAddress()) {
                $address = AddressParser::parse($subject->address);
                $values += ['street' => $address['street'], 'postalCode' => $address['postalCode'], 'city' => $address['city']];
            }
            foreach ($this->updater->apply($contractor, $values, DataSource::WhiteList) as $entry) {
                $this->em->persist($entry);
            }
        }

        $listed = array_map(static fn (string $n): string => ContractorBankAccount::normalize($n), $subject->accountNumbers);
        foreach ($contractor->getBankAccounts() as $account) {
            $plain = preg_replace('/^[A-Z]{2}/', '', $account->getNumber()) ?? $account->getNumber();
            $account->markWhiteList(\in_array($plain, $listed, true) || \in_array($account->getNumber(), $listed, true));
        }
        foreach ($listed as $number) {
            if (null === $contractor->findBankAccount($number) && null === $contractor->findBankAccount('PL'.$number)) {
                $account = new ContractorBankAccount($contractor, $number, DataSource::WhiteList);
                $account->markWhiteList(true);
                $contractor->addBankAccount($account);
            }
        }

        return true;
    }

    /**
     * Wersja bez wyjątków do użycia w trakcie synchronizacji.
     */
    public function enrichQuietly(Contractor $contractor): void
    {
        try {
            $this->enrich($contractor);
        } catch (WhiteListException $e) {
            $this->logger->warning('Biała lista: {message}', ['message' => $e->getMessage(), 'nip' => $contractor->getNip()]);
        }
    }
}
