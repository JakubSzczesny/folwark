<?php

declare(strict_types=1);

namespace App\Version;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Informacja o zainstalowanej wersji zapisana w var/wersja.json.
 *
 * Plik odświeża `app:wersja` (uruchamiane przez composer install, więc też przy każdej aktualizacji serwera).
 * Data aktualizacji zmienia się tylko wtedy, gdy zmienił się commit.
 */
class ReleaseInfo
{
    public const int CHANGES = 15;

    private ?Release $release = null;
    private bool $loaded = false;

    public function __construct(
        private readonly GitHistory $git,
        #[Autowire('%app.release_file%')] private readonly string $file,
    ) {
    }

    public function current(): ?Release
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $this->release = $this->read();
        }

        return $this->release;
    }

    /**
     * Zapisuje informację o wersji na podstawie historii git.
     */
    public function refresh(?\DateTimeImmutable $now = null): Release
    {
        $stored = $this->read();
        $commit = $this->git->head();
        $changes = $this->git->recentChanges(self::CHANGES);

        if (null !== $stored && $stored->commit === $commit) {
            $updatedAt = $stored->updatedAt;
            $previous = $stored->previousCommit;
        } else {
            $updatedAt = $now ?? new \DateTimeImmutable();
            $previous = $stored?->commit;
        }

        $since = null !== $previous ? $this->git->commitsSince($previous) : null;
        if (null !== $since) {
            $since = array_flip($since);
            $changes = array_map(static fn (ReleaseChange $c) => new ReleaseChange($c->commit, $c->date, $c->subject, isset($since[$c->commit])), $changes);
        }

        $release = new Release('1.'.$this->git->commitCount(), $commit, $this->git->commitDate(), $updatedAt, $previous, $changes);

        if (!is_dir(\dirname($this->file))) {
            mkdir(\dirname($this->file), 0o775, true);
        }
        file_put_contents($this->file, json_encode($release->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
        $this->release = $release;
        $this->loaded = true;

        return $release;
    }

    private function read(): ?Release
    {
        if (!is_file($this->file)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($this->file), true, 16, \JSON_THROW_ON_ERROR);

            return \is_array($data) ? Release::fromArray($data) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
