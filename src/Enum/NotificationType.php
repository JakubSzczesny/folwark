<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case NewInvoices = 'new_invoices';
    case PaymentDue = 'payment_due';
    case OrderApproaching = 'order_approaching';
    case OrderOverdue = 'order_overdue';
    case MachineInsurance = 'machine_insurance';
    case MachineInspection = 'machine_inspection';
    case MachineService = 'machine_service';
    case ContractExpiring = 'contract_expiring';
    case KsefError = 'ksef_error';

    public function label(): string
    {
        return match ($this) {
            self::NewInvoices => 'Nowe faktury',
            self::PaymentDue => 'Termin płatności',
            self::OrderApproaching => 'Zbliża się zamówienie',
            self::OrderOverdue => 'Zamówienie po terminie',
            self::MachineInsurance => 'Ubezpieczenie maszyny',
            self::MachineInspection => 'Przegląd techniczny',
            self::MachineService => 'Zaplanowany serwis',
            self::ContractExpiring => 'Umowa wygasa',
            self::KsefError => 'Błąd synchronizacji KSeF',
        };
    }

    /**
     * @return list<UserRole>
     */
    public function defaultRecipients(): array
    {
        return match ($this) {
            self::NewInvoices => [UserRole::Owner, UserRole::Accountant],
            self::MachineService => [UserRole::Owner, UserRole::Worker],
            default => [UserRole::Owner],
        };
    }
}
