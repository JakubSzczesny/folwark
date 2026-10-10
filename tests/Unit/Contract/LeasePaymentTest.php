<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contract;

use App\Entity\Contract;
use App\Enum\ContractKind;
use App\Enum\ContractStatus;
use App\Enum\PaymentFrequency;
use PHPUnit\Framework\TestCase;

final class LeasePaymentTest extends TestCase
{
    public function testNextQuarterlyPayment(): void
    {
        $contract = self::lease(PaymentFrequency::Quarterly, '2024-04-20');

        self::assertSame('2026-10-20', $contract->nextPaymentDate(new \DateTimeImmutable('2026-10-11'))?->format('Y-m-d'));
        self::assertSame('2026-10-20', $contract->nextPaymentDate(new \DateTimeImmutable('2026-10-20'))?->format('Y-m-d'), 'W dniu płatności to wciąż ta płatność');
        self::assertSame('2027-01-20', $contract->nextPaymentDate(new \DateTimeImmutable('2026-10-21'))?->format('Y-m-d'));
    }

    public function testMonthEndIsClamped(): void
    {
        $contract = self::lease(PaymentFrequency::Monthly, '2026-01-31');

        self::assertSame('2026-02-28', $contract->nextPaymentDate(new \DateTimeImmutable('2026-02-01'))?->format('Y-m-d'));
        self::assertSame('2026-03-31', $contract->nextPaymentDate(new \DateTimeImmutable('2026-03-01'))?->format('Y-m-d'));
    }

    public function testNoPaymentAfterContractEndsOrForOtherKinds(): void
    {
        $contract = self::lease(PaymentFrequency::Yearly, '2020-03-01')->setValidTo(new \DateTimeImmutable('2026-12-31'));
        self::assertNull($contract->nextPaymentDate(new \DateTimeImmutable('2026-10-11')));

        $sale = self::lease(PaymentFrequency::Yearly, '2020-03-01')->setKind(ContractKind::Sale);
        self::assertNull($sale->nextPaymentDate(new \DateTimeImmutable('2026-10-11')));

        $finished = self::lease(PaymentFrequency::Yearly, '2020-03-01')->setStatus(ContractStatus::Finished);
        self::assertNull($finished->nextPaymentDate(new \DateTimeImmutable('2026-10-11')));
    }

    public function testYearlyRent(): void
    {
        self::assertSame(14400.0, self::lease(PaymentFrequency::Quarterly, '2024-04-20')->yearlyRent());
    }

    private static function lease(PaymentFrequency $frequency, string $first): Contract
    {
        return (new Contract())
            ->setKind(ContractKind::Lease)
            ->setRentAmount('3600.00')
            ->setPaymentFrequency($frequency)
            ->setFirstPaymentDate(new \DateTimeImmutable($first));
    }
}
