<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Repository\InvoiceRepository;
use App\Ui\QueryParam;
use App\Ui\Sort;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtry listy faktur – odczytywane z adresu URL i zamieniane z powrotem na parametry.
 */
final class InvoiceFilter
{
    public const array TABS = ['zakupowe' => InvoiceKind::Cost, 'sprzedazowe' => InvoiceKind::Income, 'wszystkie' => null];

    /** Dawne nazwy zakładek – stare linki i zapamiętane filtry dalej działają. */
    private const array TAB_ALIASES = ['kosztowe' => 'zakupowe', 'przychodowe' => 'sprzedazowe'];

    public const array PERIODS = [
        'ten-miesiac' => 'Ten miesiąc',
        'poprzedni-miesiac' => 'Poprzedni miesiąc',
        'ten-rok' => 'Ten rok',
        'zakres' => 'Własny zakres',
        'wszystko' => 'Wszystko',
    ];

    public string $tab = 'zakupowe';
    public string $period = 'wszystko';
    public ?\DateTimeImmutable $from = null;
    public ?\DateTimeImmutable $to = null;
    public ?int $contractorId = null;
    public ?InvoiceStatus $status = null;
    public ?PaymentStatus $payment = null;
    public ?int $costCategoryId = null;
    /** Tylko faktury, na których któraś pozycja nie ma towaru z kartoteki. */
    public bool $unassigned = false;
    public ?string $amountFrom = null;
    public ?string $amountTo = null;
    public ?string $text = null;
    public Sort $sort;

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;
        $filter = new self();
        $tab = $query->getString('rodzaj');
        $tab = self::TAB_ALIASES[$tab] ?? $tab;
        $filter->tab = \array_key_exists($tab, self::TABS) ? $tab : 'zakupowe';
        $filter->period = \array_key_exists($period = $query->getString('okres'), self::PERIODS) ? $period : 'wszystko';
        $filter->from = self::date($query->getString('od'));
        $filter->to = self::date($query->getString('do'));
        $filter->contractorId = QueryParam::id($query, 'kontrahent');
        $filter->status = InvoiceStatus::tryFrom($query->getString('status'));
        $filter->payment = PaymentStatus::tryFrom($query->getString('platnosc'));
        $filter->costCategoryId = QueryParam::id($query, 'kategoria');
        $filter->unassigned = $query->getBoolean('bez_towaru');
        $filter->amountFrom = self::amount($query->getString('kwota_od'));
        $filter->amountTo = self::amount($query->getString('kwota_do'));
        $filter->text = '' !== ($text = trim($query->getString('szukaj'))) ? $text : null;
        $filter->sort = Sort::fromRequest($request, InvoiceRepository::SORT, 'data', 'desc', ['netto', 'brutto']);

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
            'bez_towaru' => $this->unassigned ? 1 : null,
            'kwota_od' => $this->amountFrom,
            'kwota_do' => $this->amountTo,
            'szukaj' => $this->text,
        ] + $this->sort->toQuery(), static fn (mixed $v): bool => null !== $v && '' !== $v);
    }

    /**
     * Liczba ustawionych filtrów – bez zakładki i sortowania.
     */
    public function activeFilterCount(): int
    {
        return \count(array_diff_key($this->toQuery(), $this->sort->toQuery())) - 1;
    }

    public function hasActiveFilters(): bool
    {
        return $this->activeFilterCount() > 0;
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
