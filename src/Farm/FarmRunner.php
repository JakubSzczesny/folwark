<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\Farm;
use App\Repository\FarmRepository;
use Psr\Log\LoggerInterface;

/**
 * Uruchamia kod w kontekście wybranego gospodarstwa – dla zadań w tle i poleceń konsoli.
 */
final class FarmRunner
{
    public function __construct(
        private readonly FarmRepository $farms,
        private readonly FarmContext $context,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(Farm): T $callback
     *
     * @return T
     */
    public function within(Farm $farm, callable $callback): mixed
    {
        $previous = $this->context->getFarm();
        $previousRole = $this->context->getRole();
        $this->context->set($farm);

        try {
            return $callback($farm);
        } finally {
            null !== $previous ? $this->context->set($previous, $previousRole) : $this->context->clear();
        }
    }

    /**
     * Błąd w jednym gospodarstwie nie zatrzymuje pozostałych.
     *
     * @param callable(Farm): mixed $callback
     */
    public function forEach(callable $callback): void
    {
        foreach ($this->farms->findBy([], ['id' => 'ASC']) as $farm) {
            try {
                $this->within($farm, $callback);
            } catch (\Throwable $e) {
                $this->logger->error('Zadanie dla gospodarstwa {farm} nie powiodło się.', ['farm' => $farm->getId(), 'exception' => $e]);
            }
        }
    }

    public function find(?int $id): ?Farm
    {
        return null === $id ? null : $this->farms->find($id);
    }
}
