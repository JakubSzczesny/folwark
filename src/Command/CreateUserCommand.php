<?php

declare(strict_types=1);

namespace App\Command;

use App\Setup\UserCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Zakłada konto użytkownika (dostęp do gospodarstwa nadaje jego właściciel w aplikacji)')]
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
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Hasło (gdy brak – pytanie)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $input->getOption('password') ?? $io->askHidden('Hasło (min. 10 znaków)');

        try {
            $this->userCreator->create((string) $input->getArgument('email'), (string) $input->getArgument('name'), (string) $password);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Konto założone.');

        return Command::SUCCESS;
    }
}
