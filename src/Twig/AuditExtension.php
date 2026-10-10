<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\AuditLog;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use Twig\Attribute\AsTwigFunction;

/**
 * Czytelny opis wpisu z dziennika zdarzeń (historia zmian na kartach obiektów).
 */
final class AuditExtension
{
    private const array ACTIONS = [
        'read' => 'Przeczytana',
        'paid' => 'Oznaczona jako opłacona',
        'unpaid' => 'Cofnięto oznaczenie opłacenia',
        'notes' => 'Zmieniono uwagi',
        'goods' => 'Przypisano towary',
        'create' => 'Utworzono',
        'update' => 'Zmieniono dane',
        'approve' => 'Zatwierdzono',
        'cancel' => 'Anulowano',
        'tare' => 'Zmiana tary',
        'mapping' => 'Zmiana mapowania',
        'use' => 'Użycie tokenu',
        'change' => 'Zmiana ustawienia',
        'finished' => 'Synchronizacja zakończona',
        'failed' => 'Synchronizacja nieudana',
    ];

    #[AsTwigFunction('audit_text')]
    public static function text(AuditLog $log): string
    {
        $changes = $log->getChanges();

        return match ($log->getAction()) {
            'status' => 'Status: '.self::status($changes['new'] ?? null),
            'kind' => 'Klasyfikacja: '.(InvoiceKind::tryFrom((string) ($changes['new'] ?? ''))?->label() ?? '—'),
            'contract' => 'Umowa: '.($changes['new'] ?? 'brak'),
            default => self::ACTIONS[$log->getAction()] ?? $log->getAction(),
        };
    }

    private static function status(mixed $value): string
    {
        return InvoiceStatus::tryFrom((string) $value)?->label() ?? (string) $value;
    }
}
