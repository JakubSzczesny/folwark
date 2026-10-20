<?php

declare(strict_types=1);

namespace App\Calendar\Source;

use App\Calendar\CalendarEntry;
use App\Calendar\CalendarSource;
use App\Entity\Contract;
use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use App\Security\Permission;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Koniec obowiązywania umów i terminy płatności dzierżaw.
 */
final class ContractSource implements CalendarSource
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly UrlGeneratorInterface $urls,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function label(): string
    {
        return 'Umowy';
    }

    public function permission(): string
    {
        return Permission::CONTRACT_VIEW;
    }

    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = [];

        /** @var list<Contract> $ending */
        $ending = $this->contracts->createQueryBuilder('k')
            ->join('k.contractor', 'c')
            ->addSelect('c')
            ->where('k.status = :active')
            ->andWhere('k.validTo BETWEEN :from AND :to')
            ->setParameter('active', ContractStatus::Active)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();
        foreach ($ending as $contract) {
            $validTo = $contract->getValidTo();
            \assert(null !== $validTo);
            $entries[] = CalendarEntry::deadline('Koniec umowy '.$contract->getNumber(), $validTo, $this->label(), $this->url($contract), $contract->getContractor()?->getDisplayName());
        }

        $amounts = $this->auth->isGranted(Permission::AMOUNTS_VIEW);
        foreach ($this->contracts->findActiveLeases() as $contract) {
            for ($date = $contract->nextPaymentDate($from); null !== $date && $date <= $to; $date = $contract->nextPaymentDate($date->modify('+1 day'))) {
                $entries[] = CalendarEntry::deadline(
                    'Dzierżawa: '.$contract->getContractor()?->getDisplayName(),
                    $date,
                    $this->label(),
                    $this->url($contract),
                    $contract->getNumber().($amounts && null !== $contract->getRentAmount() ? ' · '.FormatExtension::money($contract->getRentAmount()) : ''),
                );
            }
        }

        return $entries;
    }

    private function url(Contract $contract): string
    {
        return $this->urls->generate('app_contract_show', ['id' => $contract->getId()]);
    }
}
