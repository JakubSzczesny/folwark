<?php

declare(strict_types=1);

namespace App\Command;

use App\Farm\FarmCreator;
use App\Repository\UserRepository;
use App\Setup\UserCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:install', description: 'Zakłada pierwsze konto i gospodarstwo (gdy w bazie nie ma jeszcze użytkowników)')]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserCreator $userCreator,
        private readonly FarmCreator $farmCreator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail właściciela')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Imię i nazwisko właściciela')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Hasło właściciela')
            ->addOption('farm', null, InputOption::VALUE_REQUIRED, 'Nazwa gospodarstwa')
            ->addOption('nip', null, InputOption::VALUE_REQUIRED, 'NIP gospodarstwa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->users->count([]) > 0) {
            $io->success('W bazie są już użytkownicy – kolejne konta zakłada się przez rejestrację, a gospodarstwa w aplikacji.');

            return Command::SUCCESS;
        }

        $email = $input->getOption('email') ?? $io->ask('E-mail właściciela');
        $name = $input->getOption('name') ?? $io->ask('Imię i nazwisko', 'Właściciel');
        $password = $input->getOption('password') ?? $io->askHidden('Hasło (min. 10 znaków)');
        $farmName = $input->getOption('farm') ?? $io->ask('Nazwa gospodarstwa', 'Gospodarstwo rolne');
        $nip = $input->getOption('nip') ?? $io->ask('NIP gospodarstwa (Enter – pomiń)');

        try {
            $user = $this->userCreator->create((string) $email, (string) $name, (string) $password);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $farm = $this->farmCreator->create((string) $farmName, \is_string($nip) && '' !== $nip ? $nip : null, $user);
        $io->success(\sprintf('Założono konto %s i gospodarstwo „%s”. Zaloguj się w przeglądarce.', $user->getEmail(), $farm->getName()));

        return Command::SUCCESS;
    }
}
