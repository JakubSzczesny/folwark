<?php

declare(strict_types=1);

namespace App\Ksef\Fake;

use App\Ksef\Dto\InvoiceMetadata;
use App\Ksef\Dto\MetadataPage;
use App\Ksef\Dto\MetadataQuery;
use App\Ksef\KsefClient;
use App\Ksef\KsefException;
use App\Ksef\KsefIdentity;
use App\Ksef\SubjectType;
use App\Ksef\Xml\InvoiceXmlParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Symulator KSeF: faktury to pliki XML w katalogu (KSEF_FAKE_DIR) oraz faktury dodane w testach.
 * Plik index.json (opcjonalny) podaje numer KSeF i datę trwałego zapisu dla każdego pliku.
 */
class FakeKsefClient implements KsefClient, ResetInterface
{
    /** @var array<string, array{xml: string, storedAt: \DateTimeImmutable}>|null */
    private ?array $loaded = null;

    /** @var array<string, array{xml: string, storedAt: \DateTimeImmutable}> */
    private array $added = [];

    private int $requests = 0;

    /** @var (\Closure(MetadataQuery): ?KsefException)|null */
    private ?\Closure $failure = null;

    /** @var array<string, true> */
    private array $unreadable = [];

    public function __construct(
        #[Autowire('%env(resolve:KSEF_FAKE_DIR)%')]
        private readonly string $directory,
        private readonly KsefIdentity $identity,
        private readonly InvoiceXmlParser $parser,
    ) {
    }

    public function add(string $xml, ?\DateTimeImmutable $storedAt = null, ?string $ksefNumber = null): string
    {
        $parsed = $this->parser->parse($xml);
        $ksefNumber ??= self::ksefNumber($parsed->seller->nip ?? '0000000000', $parsed->issueDate, $parsed->number);
        $this->added[$ksefNumber] = ['xml' => $xml, 'storedAt' => $storedAt ?? new \DateTimeImmutable()];

        return $ksefNumber;
    }

    /**
     * Awaria KSeF w testach: wyjątek zwrócony przez $failure zastępuje odpowiedź na zapytanie (null – zapytanie przechodzi).
     *
     * @param (\Closure(MetadataQuery): ?KsefException)|null $failure
     */
    public function failWhen(?\Closure $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * Pozycja, której metadanych klient nie potrafi odczytać – trafia do odrzuconych, tak jak w HttpKsefClient.
     */
    public function markUnreadable(string $ksefNumber, bool $unreadable = true): void
    {
        if ($unreadable) {
            $this->unreadable[$ksefNumber] = true;
        } else {
            unset($this->unreadable[$ksefNumber]);
        }
    }

    public function requestCount(): int
    {
        return $this->requests;
    }

    public function queryMetadata(MetadataQuery $query): MetadataPage
    {
        ++$this->requests;
        $failure = null !== $this->failure ? ($this->failure)($query) : null;
        if (null !== $failure) {
            throw $failure;
        }

        $contextNip = $this->identity->nip();
        $to = $query->to ?? new \DateTimeImmutable();

        $matching = [];
        foreach ($this->invoices() as $ksefNumber => $entry) {
            if ($entry['storedAt'] < $query->from || $entry['storedAt'] > $to) {
                continue;
            }

            $metadata = $this->metadata($ksefNumber, $entry['xml'], $entry['storedAt']);
            $isSeller = $metadata->sellerNip === $contextNip;
            $isBuyer = 'Nip' === $metadata->buyerIdentifierType && $metadata->buyerIdentifier === $contextNip;
            if ((SubjectType::Seller === $query->subjectType && $isSeller) || (SubjectType::Buyer === $query->subjectType && $isBuyer)) {
                $matching[] = $metadata;
            }
        }

        usort($matching, static fn (InvoiceMetadata $a, InvoiceMetadata $b): int => [$a->permanentStorageDate, $a->ksefNumber] <=> [$b->permanentStorageDate, $b->ksefNumber]);
        $page = \array_slice($matching, $query->pageOffset * $query->pageSize, $query->pageSize);
        $rejected = array_values(array_map(
            static fn (InvoiceMetadata $m): string => $m->ksefNumber.' (nieczytelne metadane)',
            array_filter($page, fn (InvoiceMetadata $m): bool => isset($this->unreadable[$m->ksefNumber])),
        ));
        $page = array_values(array_filter($page, fn (InvoiceMetadata $m): bool => !isset($this->unreadable[$m->ksefNumber])));

        // Jak w KSeF: HWM to punkt kompletności całego systemu (tu: teraz), niezależny od końca okna zapytania.
        return new MetadataPage($page, ($query->pageOffset + 1) * $query->pageSize < \count($matching), false, new \DateTimeImmutable(), $rejected);
    }

    public function downloadInvoice(string $ksefNumber): string
    {
        ++$this->requests;
        $invoices = $this->invoices();
        if (!isset($invoices[$ksefNumber])) {
            throw new KsefException(\sprintf('Faktura %s nie istnieje w symulatorze KSeF.', $ksefNumber));
        }

        return $invoices[$ksefNumber]['xml'];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): string
    {
        return \sprintf('Symulator KSeF: %d faktur dostępnych w %s.', \count($this->invoices()), $this->directory);
    }

    public function reset(): void
    {
        $this->loaded = null;
    }

    public static function ksefNumber(string $sellerNip, \DateTimeImmutable $issueDate, string $invoiceNumber): string
    {
        $hash = strtoupper(hash('sha256', $sellerNip.'|'.$invoiceNumber));

        return \sprintf('%s-%s-%s-%s-%s', $sellerNip, $issueDate->format('Ymd'), substr($hash, 0, 6), substr($hash, 6, 6), substr($hash, 12, 2));
    }

    /**
     * @return array<string, array{xml: string, storedAt: \DateTimeImmutable}>
     */
    private function invoices(): array
    {
        if (null === $this->loaded) {
            $this->loaded = $this->loadDirectory();
        }

        return $this->added + $this->loaded;
    }

    /**
     * @return array<string, array{xml: string, storedAt: \DateTimeImmutable}>
     */
    private function loadDirectory(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $index = [];
        $indexFile = $this->directory.'/index.json';
        if (is_file($indexFile)) {
            foreach ((array) json_decode((string) file_get_contents($indexFile), true) as $entry) {
                $index[(string) $entry['file']] = $entry;
            }
        }

        $invoices = [];
        foreach (glob($this->directory.'/*.xml') ?: [] as $file) {
            $xml = (string) file_get_contents($file);
            $entry = $index[basename($file)] ?? null;
            if (null !== $entry) {
                $invoices[(string) $entry['ksefNumber']] = ['xml' => $xml, 'storedAt' => new \DateTimeImmutable((string) $entry['permanentStorageDate'])];
                continue;
            }

            $parsed = $this->parser->parse($xml);
            $ksefNumber = self::ksefNumber($parsed->seller->nip ?? '0000000000', $parsed->issueDate, $parsed->number);
            $invoices[$ksefNumber] = ['xml' => $xml, 'storedAt' => $parsed->issueDate->setTime(12, 0)];
        }

        return $invoices;
    }

    private function metadata(string $ksefNumber, string $xml, \DateTimeImmutable $storedAt): InvoiceMetadata
    {
        $parsed = $this->parser->parse($xml);
        $net = (float) ($parsed->net ?? 0);
        $vat = (float) ($parsed->vat ?? 0);

        return new InvoiceMetadata(
            ksefNumber: $ksefNumber,
            invoiceNumber: $parsed->number,
            issueDate: $parsed->issueDate,
            acquisitionDate: $storedAt,
            permanentStorageDate: $storedAt,
            sellerNip: (string) $parsed->seller->nip,
            sellerName: $parsed->seller->name,
            buyerIdentifierType: null !== $parsed->buyer->nip ? 'Nip' : 'None',
            buyerIdentifier: $parsed->buyer->nip,
            buyerName: $parsed->buyer->name,
            netAmount: number_format($net, 2, '.', ''),
            vatAmount: number_format($vat, 2, '.', ''),
            grossAmount: $parsed->gross ?? number_format($net + $vat, 2, '.', ''),
            currency: $parsed->currency,
            invoiceType: self::apiType($parsed->kindCode),
            formCode: $parsed->formCode,
        );
    }

    private static function apiType(string $kindCode): string
    {
        return match ($kindCode) {
            'KOR' => 'Kor',
            'ZAL' => 'Zal',
            'ROZ' => 'Roz',
            'UPR' => 'Upr',
            'KOR_ZAL' => 'KorZal',
            'KOR_ROZ' => 'KorRoz',
            'VAT_RR' => 'VatRr',
            'KOR_VAT_RR' => 'KorVatRr',
            default => 'Vat',
        };
    }
}
