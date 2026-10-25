<?php

declare(strict_types=1);

namespace App\Tests\Unit\Version;

use App\Version\GitHistory;
use App\Version\ReleaseInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ReleaseInfoTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/folwark-wersja-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/var', 0o777, true);
        $this->git('init', '-q');
        $this->commit('Etap 1: szkielet aplikacji');
        $this->commit('Faktury z KSeF (#2)');
    }

    protected function tearDown(): void
    {
        try {
            (new Filesystem())->remove($this->dir);
        } catch (IOException) {
            // Windows: pliki obiektów gita bywają tylko do odczytu – zostają w katalogu tymczasowym.
        }
    }

    public function testRecordsVersionAndMarksChangesFromLastUpdate(): void
    {
        $info = $this->info();
        $first = $info->refresh(new \DateTimeImmutable('2026-10-24 03:15'));

        self::assertSame('1.2', $first->version);
        self::assertSame(['Faktury z KSeF', 'Etap 1: szkielet aplikacji'], array_map(static fn ($c) => $c->subject, $first->changes));
        self::assertSame([], $first->newChanges(), 'Przy pierwszym zapisie nie wiadomo, co jest nowe');

        // Uruchomienie bez nowych commitów nie zmienia daty aktualizacji.
        self::assertEquals(new \DateTimeImmutable('2026-10-24 03:15'), $this->info()->refresh(new \DateTimeImmutable('2026-10-24 12:00'))->updatedAt);

        $this->commit('Wersja w stopce');
        $this->commit('Lista zmian w Ustawieniach');
        $second = $this->info()->refresh(new \DateTimeImmutable('2026-10-25 03:16'));

        self::assertSame('1.4', $second->version);
        self::assertEquals(new \DateTimeImmutable('2026-10-25 03:16'), $second->updatedAt);
        self::assertSame($first->commit, $second->previousCommit);
        self::assertSame(['Lista zmian w Ustawieniach', 'Wersja w stopce'], array_map(static fn ($c) => $c->subject, $second->newChanges()));

        $read = $this->info()->current();
        self::assertNotNull($read);
        self::assertSame($second->commit, $read->commit);
        self::assertCount(2, $read->newChanges());
    }

    public function testMissingOrBrokenFileMeansNoVersion(): void
    {
        self::assertNull($this->info()->current());

        file_put_contents($this->dir.'/var/wersja.json', '{"version": ');
        self::assertNull($this->info()->current());
    }

    private function info(): ReleaseInfo
    {
        return new ReleaseInfo(new GitHistory($this->dir), $this->dir.'/var/wersja.json');
    }

    private function commit(string $subject): void
    {
        file_put_contents($this->dir.'/plik.txt', $subject);
        $this->git('add', 'plik.txt');
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '-q', '-m', $subject);
    }

    private function git(string ...$args): void
    {
        (new Process(['git', ...$args], $this->dir))->mustRun();
    }
}
