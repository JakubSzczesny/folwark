<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Setup\StartupData;
use App\Setup\UserCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:install', description: 'Wstawia dane startowe i zakłada konto właściciela, jeśli nie ma żadnego użytkownika')]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly StartupData $startupData,
        private readonly UserRepository $users,
        private readonly UserCreator $userCreator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail właściciela')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Imię i nazwisko właściciela')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Hasło właściciela');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $seeded = $this->startupData->seed();
        $io->writeln([] === $seeded ? 'Dane startowe już istnieją.' : 'Dodano: '.implode(', ', $seeded).'.');

        if ($this->users->count([]) > 0) {
            $io->success('Gotowe.');

            return Command::SUCCESS;
        }

        $email = $input->getOption('email') ?? $io->ask('E-mail właściciela');
        $name = $input->getOption('name') ?? $io->ask('Imię i nazwisko', 'Właściciel');
        $password = $input->getOption('password') ?? $io->askHidden('Hasło (min. 10 znaków)');

        $errors = $this->userCreator->create((string) $email, (string) $name, (string) $password, UserRole::Owner);
        if ([] !== $errors) {
            $io->error($errors);

            return Command::FAILURE;
        }

        $io->success(\sprintf('Założono konto właściciela %s. Zaloguj się w przeglądarce.', $email));

        return Command::SUCCESS;
    }
}
