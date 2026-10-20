<?php

declare(strict_types=1);

namespace App\Calendar;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Moduł, który pokazuje swoje terminy w kalendarzu (tylko do odczytu).
 */
#[AutoconfigureTag]
interface CalendarSource
{
    /** Nazwa grupy w legendzie, np. „Płatności faktur”. */
    public function label(): string;

    /** Uprawnienie wymagane do zobaczenia terminów – wyłączony moduł nie ma uprawnień. */
    public function permission(): string;

    /**
     * @return list<CalendarEntry>
     */
    public function entries(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
