<?php

declare(strict_types=1);

namespace App\Command;

use App\Version\ReleaseInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:wersja', description: 'Zapisuje numer wersji, datę aktualizacji i ostatnie zmiany z historii git (stopka i Ustawienia → O programie)')]
final class VersionCommand extends Command
{
    public function __construct(
        private readonly ReleaseInfo $releaseInfo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $release = $this->releaseInfo->refresh();
        } catch (\RuntimeException|\JsonException $e) {
            // Bez gita (np. kopia bez katalogu .git) aplikacja działa dalej, tylko bez numeru wersji.
            $io->warning('Nie udało się odczytać wersji z git: '.$e->getMessage());

            return Command::SUCCESS;
        }

        $io->writeln(\sprintf('Folwark %s (%s), zaktualizowano %s.', $release->version, $release->shortCommit(), $release->updatedAt->format('Y-m-d H:i')));

        return Command::SUCCESS;
    }
}
