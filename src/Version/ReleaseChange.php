<?php

declare(strict_types=1);

namespace App\Version;

/**
 * Jeden commit z historii – opis zmiany pokazywany w Ustawieniach.
 */
final readonly class ReleaseChange
{
    public function __construct(
        public string $commit,
        public \DateTimeImmutable $date,
        public string $subject,
        public bool $new = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['commit' => $this->commit, 'date' => $this->date->format(\DATE_ATOM), 'subject' => $this->subject, 'new' => $this->new];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['commit'] ?? null) ? $data['commit'] : '',
            new \DateTimeImmutable(\is_string($data['date'] ?? null) ? $data['date'] : 'now'),
            \is_string($data['subject'] ?? null) ? $data['subject'] : '',
            true === ($data['new'] ?? false),
        );
    }
}
