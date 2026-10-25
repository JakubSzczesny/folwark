<?php

declare(strict_types=1);

namespace App\Version;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Odczyt historii z repozytorium git, z którego działa aplikacja (serwer aktualizuje się przez git pull).
 */
class GitHistory
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $repositoryDir,
    ) {
    }

    public function head(): string
    {
        return $this->git('rev-parse', 'HEAD');
    }

    public function commitCount(): int
    {
        return (int) $this->git('rev-list', '--count', 'HEAD');
    }

    public function commitDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->git('log', '-1', '--format=%cI', 'HEAD'));
    }

    /**
     * Ostatnie commity bez scaleń gałęzi, najnowsze na początku.
     *
     * @return list<ReleaseChange>
     */
    public function recentChanges(int $limit): array
    {
        $changes = [];
        $log = $this->git('log', '-n', (string) $limit, '--no-merges', '--format=%H%x1f%cI%x1f%s', 'HEAD');
        foreach (explode("\n", $log) as $line) {
            $parts = explode("\x1f", $line, 3);
            if (3 === \count($parts)) {
                // Numer PR z GitHuba („… (#6)”) nic nie mówi w aplikacji.
                $changes[] = new ReleaseChange($parts[0], new \DateTimeImmutable($parts[1]), (string) preg_replace('/\s*\(#\d+\)$/', '', $parts[2]));
            }
        }

        return $changes;
    }

    /**
     * Commity dodane po $commit albo null, gdy $commit nie jest przodkiem obecnej wersji.
     *
     * @return list<string>|null
     */
    public function commitsSince(string $commit): ?array
    {
        try {
            $this->git('merge-base', '--is-ancestor', $commit, 'HEAD');
        } catch (\RuntimeException) {
            return null;
        }
        $list = $this->git('rev-list', $commit.'..HEAD');

        return '' === $list ? [] : explode("\n", $list);
    }

    private function git(string ...$args): string
    {
        // safe.directory: aktualizacja działa jako root, a katalog aplikacji może należeć do innego użytkownika.
        $process = new Process(['git', '-c', 'safe.directory='.$this->repositoryDir, ...$args], $this->repositoryDir);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'git '.implode(' ', $args).' – błąd '.$process->getExitCode());
        }

        return trim($process->getOutput());
    }
}
