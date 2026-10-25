<?php

declare(strict_types=1);

namespace App\Version;

/**
 * Wersja zainstalowanego Folwarku: numer, commit, kiedy trafił na serwer i ostatnie zmiany z historii git.
 */
final readonly class Release
{
    /**
     * @param list<ReleaseChange> $changes najnowsze na początku
     */
    public function __construct(
        public string $version,
        public string $commit,
        public \DateTimeImmutable $commitDate,
        public \DateTimeImmutable $updatedAt,
        public ?string $previousCommit,
        public array $changes,
    ) {
    }

    public function shortCommit(): string
    {
        return substr($this->commit, 0, 7);
    }

    /**
     * Zmiany, które przyszły z ostatnią aktualizacją.
     *
     * @return list<ReleaseChange>
     */
    public function newChanges(): array
    {
        return array_values(array_filter($this->changes, static fn (ReleaseChange $change) => $change->new));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'commit' => $this->commit,
            'commit_date' => $this->commitDate->format(\DATE_ATOM),
            'updated_at' => $this->updatedAt->format(\DATE_ATOM),
            'previous_commit' => $this->previousCommit,
            'changes' => array_map(static fn (ReleaseChange $change) => $change->toArray(), $this->changes),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $changes = [];
        foreach (\is_array($data['changes'] ?? null) ? $data['changes'] : [] as $change) {
            if (\is_array($change)) {
                $changes[] = ReleaseChange::fromArray($change);
            }
        }
        $previous = $data['previous_commit'] ?? null;

        return new self(
            self::string($data, 'version'),
            self::string($data, 'commit'),
            new \DateTimeImmutable(self::string($data, 'commit_date')),
            new \DateTimeImmutable(self::string($data, 'updated_at')),
            \is_string($previous) ? $previous : null,
            $changes,
        );
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new \UnexpectedValueException(\sprintf('Brak pola „%s” w informacji o wersji.', $key));
        }

        return $value;
    }
}
