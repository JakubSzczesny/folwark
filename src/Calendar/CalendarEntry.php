<?php

declare(strict_types=1);

namespace App\Calendar;

/**
 * Jedna pozycja w widoku kalendarza: termin wydarzenia albo termin z innego modułu
 * (płatność faktury, koniec umowy, przegląd maszyny…).
 */
final class CalendarEntry
{
    public function __construct(
        public readonly string $title,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        /** Klasa koloru: kategoria wydarzenia albo „deadline” dla terminów z modułów. */
        public readonly string $tone,
        public readonly string $category,
        public readonly ?string $url = null,
        public readonly ?string $time = null,
        public readonly ?string $detail = null,
        public readonly ?int $eventId = null,
        public readonly bool $recurring = false,
        public readonly bool $private = false,
        public readonly bool $editable = false,
        /** Adres otwierany w oknie (szczegóły wydarzenia); terminy z modułów prowadzą wprost do obiektu. */
        public readonly ?string $modalUrl = null,
        /** Godzina rozpoczęcia „HHMM” – do kolejności w obrębie dnia. */
        public readonly ?string $sortTime = null,
    ) {
    }

    public static function deadline(string $title, \DateTimeImmutable $date, string $category, string $url, ?string $detail = null): self
    {
        $date = $date->setTime(0, 0);

        return new self($title, $date, $date, 'deadline', $category, $url, detail: $detail);
    }

    public function isSystem(): bool
    {
        return null === $this->eventId;
    }

    public function isAllDay(): bool
    {
        return null === $this->time;
    }

    public function covers(\DateTimeImmutable $day): bool
    {
        $day = $day->setTime(0, 0);

        return $day >= $this->start && $day <= $this->end;
    }

    public function isMultiDay(): bool
    {
        return $this->end > $this->start;
    }

    /**
     * Klucz sortowania w obrębie dnia: całodniowe i terminy na górze, potem wg godziny.
     */
    public function sortKey(): string
    {
        return $this->start->format('Ymd').($this->isAllDay() ? '0' : '1'.($this->sortTime ?? '')).$this->title;
    }
}
