<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Farm;
use App\Farm\FarmRunner;
use App\Ksef\KsefClient;
use App\Ksef\KsefIdentity;
use App\Ksef\KsefSynchronizer;
use App\Repository\FarmRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sprawdza połączenie z KSeF dla gospodarstw z zapisanym tokenem (to samo co „Testuj połączenie” w ustawieniach),
 * a z --sync od razu pobiera nowe faktury (jak „Synchronizuj teraz”).
 */
#[AsCommand(name: 'app:ksef:test', description: 'Sprawdza połączenie z KSeF dla gospodarstw z zapisanym tokenem')]
final class KsefTestCommand
{
    public function __construct(
        private readonly FarmRepository $farms,
        private readonly FarmRunner $runner,
        private readonly Settings $settings,
        private readonly KsefClient $client,
        private readonly KsefIdentity $identity,
        private readonly KsefSynchronizer $synchronizer,
        #[Autowire('%env(KSEF_CLIENT)%')]
        private readonly string $clientType,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Tylko to gospodarstwo (id)')] ?int $farm = null, #[Option(description: 'Po teście pobierz nowe faktury')] bool $sync = false): int
    {
        $io->writeln(\sprintf('Klient KSeF: <info>%s</info>%s', $this->clientType, 'fake' === $this->clientType ? ' (symulator – ustaw KSEF_CLIENT=http, żeby łączyć się z prawdziwym API)' : ''));

        $failed = false;
        foreach (null === $farm ? $this->farms->findBy([], ['id' => 'ASC']) : array_filter([$this->farms->find($farm)]) as $entity) {
            $this->runner->within($entity, function (Farm $farm) use ($io, $sync, &$failed): void {
                $io->section(\sprintf('#%d %s', $farm->getId(), $farm->getName()));
                if (null === $this->settings->get(SettingKey::KSEF_TOKEN)) {
                    $io->writeln('Brak tokenu – pomijam.');

                    return;
                }

                $io->writeln(\sprintf('Środowisko: %s, NIP: %s', $this->settings->get(SettingKey::KSEF_ENVIRONMENT), $this->identity->nip() ?? '—'));
                try {
                    $io->success($this->client->testConnection());
                    if ($sync) {
                        $result = $this->synchronizer->sync(true);
                        if (null === $result) {
                            $io->warning('Synchronizacja już trwa albo KSeF chwilowo blokuje zapytania – spróbuj za chwilę.');
                        } elseif ($result->isFailed()) {
                            $failed = true;
                            $io->error('Synchronizacja: '.$result->getError());
                        } else {
                            $io->success(\sprintf('Synchronizacja: %d nowych faktur (kosztowych: %d).', $result->getNewCount(), $result->getNewCostCount()));
                        }
                    }
                } catch (\Throwable $e) {
                    $failed = true;
                    $io->error($e->getMessage());
                }
            });
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
