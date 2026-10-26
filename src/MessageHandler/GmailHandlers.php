<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Farm\FarmRunner;
use App\Gmail\GmailMailbox;
use App\Gmail\MailIndexer;
use App\Message\SyncGmailIndex;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class GmailHandlers
{
    /** Co ile minut harmonogram odświeża indeks poczty. */
    public const int INTERVAL_MINUTES = 15;

    public function __construct(
        private readonly MailIndexer $indexer,
        private readonly GmailMailbox $mailbox,
        private readonly FarmRunner $runner,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler]
    public function sync(SyncGmailIndex $message): void
    {
        $farm = $this->runner->find($message->farmId);
        if (null !== $farm) {
            $this->runner->within($farm, fn () => $this->run());

            return;
        }

        $this->runner->forEach(function (): void {
            if ($this->indexer->isConfigured() && $this->indexer->isDue(self::INTERVAL_MINUTES)) {
                $this->run();
            }
        });
    }

    private function run(): void
    {
        try {
            $this->indexer->sync();
        } catch (\Throwable $e) {
            // Błąd jest zapisany w stanie indeksu i widoczny na karcie kontrahenta – nie ponawiamy.
            $this->logger->warning('Synchronizacja poczty nieudana: {message}', ['message' => $e->getMessage()]);
        } finally {
            // Połączenie z Gmailem nie może wisieć w procesie roboczym do następnego zadania.
            $this->mailbox->reset();
        }
    }
}
