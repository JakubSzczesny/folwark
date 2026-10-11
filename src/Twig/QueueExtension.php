<?php

declare(strict_types=1);

namespace App\Twig;

use Doctrine\DBAL\Connection;
use Twig\Attribute\AsTwigFunction;

/**
 * Czy zadania w tle są wykonywane – jeśli w kolejce leżą stare zadania, worker najpewniej nie działa.
 */
final class QueueExtension
{
    private const int STALE_MINUTES = 3;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Liczba zadań czekających w kolejce dłużej niż kilka minut.
     */
    #[AsTwigFunction('stalled_jobs')]
    public function stalledJobs(): int
    {
        try {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND delivered_at IS NULL AND available_at < ?',
                ['default', (new \DateTimeImmutable(\sprintf('-%d minutes', self::STALE_MINUTES)))->format('Y-m-d H:i:s')],
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
