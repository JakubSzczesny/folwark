<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtry listy faktur – odczytywane z adresu URL i zamieniane z powrotem na parametry.
 */
final class InvoiceFilter
{
    public const array TABS = ['kosztowe' => InvoiceKind::Cost, 'przychodowe' => InvoiceKind::Income, 'wszystkie' => null];

    public const array PERIODS = [
        'ten-miesiac' => 'Ten miesiąc',
        'poprzedni-miesiac' => 'Poprzedni miesiąc',
        'ten-rok' => 'Ten rok',
        'zakres' => 'Własny zakres',
        'wszystko' => 'Wszystko',
    ];

    public string $tab = 'kosztowe';
    public string $period = 'wszystko';
    public ?\DateTimeImmutable $from = null;
    public ?\DateTimeImmutable $to = null;
    public ?int $contractorId = null;
    public ?InvoiceStatus $status = null;
    public ?PaymentStatus $payment = null;
    public ?int $costCategoryId = null;
    public ?string $amountFrom = null;
    public ?string $amountTo = null;
    public ?string $text = null;
    public string $sort = 'data';
    public string $direction = 'desc';

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;
        $filter = new self();
        $filter->tab = \array_key_exists($tab = $query->getString('rodzaj'), self::TABS) ? $tab : 'kosztowe';
        $filter->period = \array_key_exists($period = $query->getString('okres'), self::PERIODS) ? $period : 'wszystko';
        $filter->from = self::date($query->getString('od'));
        $filter->to = self::date($query->getString('do'));
        $filter->contractorId = $query->getInt('kontrahent') ?: null;
        $filter->status = InvoiceStatus::tryFrom($query->getString('status'));
        $filter->payment = PaymentStatus::tryFrom($query->getString('platnosc'));
        $filter->costCategoryId = $query->getInt('kategoria') ?: null;
        $filter->amountFrom = self::amount($query->getString('kwota_od'));
        $filter->amountTo = self::amount($query->getString('kwota_do'));
        $filter->text = '' !== ($text = trim($query->getString('szukaj'))) ? $text : null;
        $filter->sort = \in_array($sort = $query->getString('sort'), ['data', 'numer', 'kontrahent', 'netto', 'brutto', 'termin'], true) ? $sort : 'data';
        $filter->direction = 'asc' === $query->getString('kierunek') ? 'asc' : 'desc';

        return $filter;
    }

    public function kind(): ?InvoiceKind
    {
        return self::TABS[$this->tab];
    }

    /**
     * @return array{?\DateTimeImmutable, ?\DateTimeImmutable} zakres dat wystawienia (włącznie)
     */
    public function dateRange(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return match ($this->period) {
            'ten-miesiac' => [$today->modify('first day of this month'), $today->modify('last day of this month')],
            'poprzedni-miesiac' => [$today->modify('first day of previous month'), $today->modify('last day of previous month')],
            'ten-rok' => [$today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y'), 12, 31)],
            'zakres' => [$this->from, $this->to],
            default => [null, null],
        };
    }

    /**
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'rodzaj' => $this->tab,
            'okres' => 'wszystko' !== $this->period ? $this->period : null,
            'od' => 'zakres' === $this->period ? $this->from?->format('Y-m-d') : null,
            'do' => 'zakres' === $this->period ? $this->to?->format('Y-m-d') : null,
            'kontrahent' => $this->contractorId,
            'status' => $this->status?->value,
            'platnosc' => $this->payment?->value,
            'kategoria' => $this->costCategoryId,
            'kwota_od' => $this->amountFrom,
            'kwota_do' => $this->amountTo,
            'szukaj' => $this->text,
            'sort' => 'data' !== $this->sort ? $this->sort : null,
            'kierunek' => 'desc' !== $this->direction ? $this->direction : null,
        ], static fn (mixed $v): bool => null !== $v && '' !== $v);
    }

    public function isActive(string $field): bool
    {
        return $field === $this->sort;
    }

    public function directionFor(string $field): string
    {
        if ($field !== $this->sort) {
            return 'data' === $field ? 'desc' : 'asc';
        }

        return 'asc' === $this->direction ? 'desc' : 'asc';
    }

    public function hasActiveFilters(): bool
    {
        return \count($this->toQuery()) > 1;
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        $date = '' !== $value ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return false === $date ? null : $date;
    }

    private static function amount(string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value));

        return is_numeric($value) ? $value : null;
    }
}
