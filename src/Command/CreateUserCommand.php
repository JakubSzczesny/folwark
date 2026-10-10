<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UserRole;
use App\Setup\UserCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Zakłada konto użytkownika')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserCreator $userCreator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::REQUIRED, 'Imię i nazwisko')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'owner, worker albo accountant', 'worker')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Hasło (gdy brak – pytanie)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = match ($input->getOption('role')) {
            'owner' => UserRole::Owner,
            'accountant' => UserRole::Accountant,
            'worker' => UserRole::Worker,
            default => null,
        };
        if (null === $role) {
            $io->error('Nieznana rola. Dozwolone: owner, worker, accountant.');

            return Command::INVALID;
        }

        $password = $input->getOption('password') ?? $io->askHidden('Hasło (min. 10 znaków)');
        $errors = $this->userCreator->create((string) $input->getArgument('email'), (string) $input->getArgument('name'), (string) $password, $role);
        if ([] !== $errors) {
            $io->error($errors);

            return Command::FAILURE;
        }

        $io->success('Konto założone.');

        return Command::SUCCESS;
    }
}
