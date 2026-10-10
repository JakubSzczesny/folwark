<?php

declare(strict_types=1);

namespace App\Contractor;

use App\Entity\Contractor;
use App\Entity\ContractorHistory;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\DataSource;

/**
 * Reguły aktualizacji danych kontrahenta. Nie zapisuje niczego do bazy – zwraca wpisy historii.
 */
final class ContractorDataUpdater
{
    /**
     * Dane z faktury nadpisują obecne tylko wtedy, gdy faktura jest nie starsza niż ta,
     * z której pochodzą obecne dane, i nie ma blokady ręcznej edycji.
     */
    public function acceptsInvoiceData(Contractor $contractor, Invoice $invoice): bool
    {
        if ($contractor->isUpdateLocked()) {
            return false;
        }

        $current = $contractor->getDataFromInvoice();

        return null === $current || $current === $invoice || $invoice->getIssueDate() >= $current->getIssueDate();
    }

    /**
     * @param array<string, ?string> $values pole => nowa wartość (null = brak danych, pomijane)
     *
     * @return list<ContractorHistory>
     */
    public function apply(Contractor $contractor, array $values, DataSource $source, ?Invoice $invoice = null, ?User $user = null): array
    {
        $history = [];
        foreach ($values as $field => $value) {
            if (null === $value || '' === trim($value)) {
                continue;
            }

            $old = $this->get($contractor, $field);
            if ($old === $value) {
                continue;
            }

            $this->set($contractor, $field, $value);
            $history[] = new ContractorHistory($contractor, $field, $old, $value, $source, $invoice, $user);
        }

        if ([] !== $history) {
            $contractor->touchData()->setDataSource($source);
        }

        return $history;
    }

    /**
     * Ręczna edycja: zapisuje różnice i zakłada blokadę, gdy zmieniono nazwę lub adres.
     *
     * @param array<string, ?string> $before stan przed edycją
     *
     * @return list<ContractorHistory>
     */
    public function recordManualEdit(Contractor $contractor, array $before, ?User $user): array
    {
        $history = [];
        $lockRelevant = false;
        foreach ($before as $field => $old) {
            $new = $this->get($contractor, $field);
            if ($old === $new) {
                continue;
            }

            $history[] = new ContractorHistory($contractor, $field, $old, $new, DataSource::Manual, null, $user);
            if ('name' === $field || \in_array($field, Contractor::ADDRESS_FIELDS, true)) {
                $lockRelevant = true;
            }
        }

        if ($lockRelevant && !$contractor->isUpdateLocked() && null !== $contractor->getNip()) {
            $contractor->setUpdateLocked(true);
            $history[] = new ContractorHistory($contractor, 'updateLocked', 'nie', 'tak', DataSource::Manual, null, $user);
        }

        if ([] !== $history) {
            $contractor->touchData()->setDataSource(DataSource::Manual);
        }

        return $history;
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, ?string>
     */
    public function snapshot(Contractor $contractor, array $fields): array
    {
        $snapshot = [];
        foreach ($fields as $field) {
            $snapshot[$field] = $this->get($contractor, $field);
        }

        return $snapshot;
    }

    private function get(Contractor $contractor, string $field): ?string
    {
        return match ($field) {
            'name' => $contractor->getName(),
            'shortName' => $contractor->getShortName(),
            'street' => $contractor->getStreet(),
            'postalCode' => $contractor->getPostalCode(),
            'city' => $contractor->getCity(),
            'country' => $contractor->getCountry(),
            'email' => $contractor->getEmail(),
            'phone' => $contractor->getPhone(),
            'contactPerson' => $contractor->getContactPerson(),
            'nip' => $contractor->getNip(),
            default => throw new \InvalidArgumentException('Nieznane pole kontrahenta: '.$field),
        };
    }

    private function set(Contractor $contractor, string $field, string $value): void
    {
        match ($field) {
            'name' => $contractor->setName($value),
            'shortName' => $contractor->setShortName($value),
            'street' => $contractor->setStreet($value),
            'postalCode' => $contractor->setPostalCode($value),
            'city' => $contractor->setCity($value),
            'country' => $contractor->setCountry($value),
            'email' => $contractor->setEmail($value),
            'phone' => $contractor->setPhone($value),
            'contactPerson' => $contractor->setContactPerson($value),
            default => throw new \InvalidArgumentException('Pola '.$field.' nie aktualizujemy automatycznie.'),
        };
    }
}
