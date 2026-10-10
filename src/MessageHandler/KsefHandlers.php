<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Farm;
use App\Entity\Invoice;
use App\Entity\User;
use App\Farm\FarmRunner;
use App\Invoice\InvoiceXmlService;
use App\Ksef\KsefException;
use App\Ksef\KsefRateLimitException;
use App\Ksef\KsefSynchronizer;
use App\Message\FetchInvoiceXml;
use App\Message\SyncKsefMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class KsefHandlers
{
    public function __construct(
        private readonly KsefSynchronizer $synchronizer,
        private readonly InvoiceXmlService $xml,
        private readonly FarmRunner $runner,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler]
    public function sync(SyncKsefMetadata $message): void
    {
        $farm = $this->runner->find($message->farmId);
        if (null !== $farm) {
            $user = null !== $message->userId ? $this->em->find(User::class, $message->userId) : null;
            $this->runner->within($farm, fn () => $this->synchronizer->sync($message->manual, $user));

            return;
        }

        $this->runner->forEach(function (Farm $farm): void {
            if ($this->synchronizer->isConfigured() && $this->synchronizer->isDue()) {
                $this->synchronizer->sync();
            }
        });
    }

    #[AsMessageHandler]
    public function fetchXml(FetchInvoiceXml $message): void
    {
        $farm = $this->runner->find($message->farmId);
        if (null === $farm) {
            return;
        }

        $this->runner->within($farm, function () use ($message): void {
            $invoice = $this->em->find(Invoice::class, $message->invoiceId);
            if (null === $invoice || $invoice->hasXml()) {
                return;
            }

            try {
                $this->xml->fetch($invoice, true);
                $this->em->flush();
            } catch (KsefRateLimitException $e) {
                $this->bus->dispatch($message, [new DelayStamp($e->retryAfterSeconds * 1000)]);
            } catch (KsefException $e) {
                $this->logger->warning('Nie udało się pobrać XML faktury {number}: {message}', [
                    'number' => $invoice->getKsefNumber(),
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}
