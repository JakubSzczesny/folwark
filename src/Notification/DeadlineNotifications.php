<?php

declare(strict_types=1);

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class DeadlineNotifications
{
    /**
     * @param iterable<DeadlineSource> $sources
     */
    public function __construct(
        #[AutowireIterator(DeadlineSource::class)]
        private readonly iterable $sources,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(?\DateTimeImmutable $today = null): void
    {
        $today ??= new \DateTimeImmutable('today');

        foreach ($this->sources as $source) {
            try {
                $source->generate($today);
            } catch (\Throwable $e) {
                $this->logger->error('Błąd generowania powiadomień: {source}', ['source' => $source::class, 'exception' => $e]);
            }
        }
    }
}
