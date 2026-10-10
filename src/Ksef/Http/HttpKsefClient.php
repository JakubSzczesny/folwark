<?php

declare(strict_types=1);

namespace App\Ksef\Http;

use App\Audit\AuditLogger;
use App\Crypto\SecretBox;
use App\Ksef\Dto\InvoiceMetadata;
use App\Ksef\Dto\MetadataPage;
use App\Ksef\Dto\MetadataQuery;
use App\Ksef\KsefClient;
use App\Ksef\KsefException;
use App\Ksef\KsefIdentity;
use App\Ksef\KsefRateLimitException;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Klient API KSeF 2.0 według dokumentacji MF (github.com/CIRFMF/ksef-docs, openapi v2).
 * Uwierzytelnienie tokenem KSeF: challenge → zaszyfrowany token → status → redeem.
 */
final class HttpKsefClient implements KsefClient
{
    private const int AUTH_POLL_ATTEMPTS = 30;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Settings $settings,
        private readonly KsefIdentity $identity,
        private readonly SecretBox $secrets,
        private readonly KsefTokenEncryptor $encryptor,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'ksef.auth_poll_interval_ms')]
        private readonly int $pollIntervalMs = 1000,
    ) {
    }

    public function queryMetadata(MetadataQuery $query): MetadataPage
    {
        $dateRange = [
            'dateType' => 'PermanentStorage',
            'from' => self::isoDate($query->from),
            'restrictToPermanentStorageHwmDate' => true,
        ];
        if (null !== $query->to) {
            $dateRange['to'] = self::isoDate($query->to);
        }

        $data = $this->authorizedJson('POST', '/invoices/query/metadata', [
            'query' => ['sortOrder' => 'Asc', 'pageOffset' => $query->pageOffset, 'pageSize' => $query->pageSize],
            'json' => ['subjectType' => $query->subjectType->value, 'dateRange' => $dateRange],
        ]);

        $invoices = array_map(
            static fn (array $row): InvoiceMetadata => InvoiceMetadata::fromApi($row),
            array_values($data['invoices'] ?? []),
        );

        return new MetadataPage(
            $invoices,
            (bool) ($data['hasMore'] ?? false),
            (bool) ($data['isTruncated'] ?? false),
            isset($data['permanentStorageHwmDate']) ? new \DateTimeImmutable((string) $data['permanentStorageHwmDate']) : null,
        );
    }

    public function downloadInvoice(string $ksefNumber): string
    {
        $response = $this->authorized('GET', '/invoices/ksef/'.rawurlencode($ksefNumber), [
            'headers' => ['Accept' => 'application/xml'],
        ]);

        return $response->getContent();
    }

    public function testConnection(): string
    {
        $this->forgetTokens();
        $this->accessToken();

        return \sprintf('Połączono z KSeF (%s) w kontekście NIP %s.', $this->environment()->label(), $this->nip());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function authorizedJson(string $method, string $path, array $options): array
    {
        $response = $this->authorized($method, $path, $options + ['headers' => ['Accept' => 'application/json']]);

        return $response->toArray();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authorized(string $method, string $path, array $options, bool $retry = true): ResponseInterface
    {
        $options['auth_bearer'] = $this->accessToken();
        $response = $this->send($method, $path, $options, false);

        if (401 === $response->getStatusCode() && $retry) {
            $this->forgetTokens();

            return $this->authorized($method, $path, $options, false);
        }

        $this->assertSuccessful($response);

        return $response;
    }

    private function accessToken(): string
    {
        $item = $this->cache->getItem($this->cacheKey());
        $cached = $item->isHit() ? $item->get() : null;
        $tokens = \is_array($cached) && isset($cached['access'], $cached['accessUntil'], $cached['refresh'], $cached['refreshUntil']) ? [
            'access' => (string) $cached['access'],
            'accessUntil' => (int) $cached['accessUntil'],
            'refresh' => (string) $cached['refresh'],
            'refreshUntil' => (int) $cached['refreshUntil'],
        ] : null;
        $now = time();

        if (null !== $tokens && $tokens['accessUntil'] > $now + 60) {
            return $tokens['access'];
        }

        if (null !== $tokens && $tokens['refreshUntil'] > $now + 60) {
            try {
                $data = $this->json('POST', '/auth/token/refresh', ['auth_bearer' => $tokens['refresh']]);
                $tokens['access'] = (string) $data['accessToken']['token'];
                $tokens['accessUntil'] = (new \DateTimeImmutable((string) $data['accessToken']['validUntil']))->getTimestamp();
                $this->store($tokens);

                return $tokens['access'];
            } catch (KsefException $e) {
                $this->logger->info('Odświeżenie tokenu KSeF nie powiodło się, ponowne uwierzytelnienie.', ['exception' => $e]);
            }
        }

        $tokens = $this->authenticate();
        $this->store($tokens);

        return $tokens['access'];
    }

    /**
     * @return array{access: string, accessUntil: int, refresh: string, refreshUntil: int}
     */
    private function authenticate(): array
    {
        $token = $this->token();
        $nip = $this->nip();

        $challenge = $this->json('POST', '/auth/challenge');
        $certificate = $this->tokenEncryptionCertificate();

        $init = $this->json('POST', '/auth/ksef-token', ['json' => [
            'challenge' => (string) $challenge['challenge'],
            'contextIdentifier' => ['type' => 'Nip', 'value' => $nip],
            'encryptedToken' => $this->encryptor->encrypt($token, (int) $challenge['timestampMs'], $certificate['certificate']),
            'publicKeyId' => $certificate['publicKeyId'],
        ]]);

        $this->audit->log('KsefToken', $nip, 'use', ['environment' => $this->environment()->value, 'operation' => 'authenticate']);

        $referenceNumber = (string) $init['referenceNumber'];
        $authenticationToken = (string) $init['authenticationToken']['token'];

        for ($attempt = 0; $attempt < self::AUTH_POLL_ATTEMPTS; ++$attempt) {
            $status = $this->json('GET', '/auth/'.rawurlencode($referenceNumber), ['auth_bearer' => $authenticationToken]);
            $code = (int) ($status['status']['code'] ?? 0);

            if (200 === $code) {
                $redeemed = $this->json('POST', '/auth/token/redeem', ['auth_bearer' => $authenticationToken]);

                return [
                    'access' => (string) $redeemed['accessToken']['token'],
                    'accessUntil' => (new \DateTimeImmutable((string) $redeemed['accessToken']['validUntil']))->getTimestamp(),
                    'refresh' => (string) $redeemed['refreshToken']['token'],
                    'refreshUntil' => (new \DateTimeImmutable((string) $redeemed['refreshToken']['validUntil']))->getTimestamp(),
                ];
            }

            if (100 !== $code) {
                $details = implode(' ', (array) ($status['status']['details'] ?? []));
                throw new KsefException(trim(\sprintf('Uwierzytelnienie w KSeF nie powiodło się: %s %s', $status['status']['description'] ?? $code, $details)));
            }

            usleep($this->pollIntervalMs * 1000);
        }

        throw new KsefException('KSeF nie zakończył uwierzytelnienia w oczekiwanym czasie. Spróbuj ponownie później.');
    }

    /**
     * @return array{certificate: string, publicKeyId: string}
     */
    private function tokenEncryptionCertificate(): array
    {
        $item = $this->cache->getItem('ksef_cert_'.$this->environment()->value);
        if ($item->isHit()) {
            return $item->get();
        }

        $certificates = $this->json('GET', '/security/public-key-certificates');
        $now = new \DateTimeImmutable();
        foreach ($certificates as $certificate) {
            if (!\in_array('KsefTokenEncryption', (array) ($certificate['usage'] ?? []), true)) {
                continue;
            }
            if (new \DateTimeImmutable((string) $certificate['validFrom']) > $now || new \DateTimeImmutable((string) $certificate['validTo']) < $now) {
                continue;
            }

            $result = ['certificate' => (string) $certificate['certificate'], 'publicKeyId' => (string) $certificate['publicKeyId']];
            $item->set($result)->expiresAfter(86400);
            $this->cache->save($item);

            return $result;
        }

        throw new KsefException('KSeF nie udostępnia ważnego klucza do szyfrowania tokenu.');
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function json(string $method, string $path, array $options = []): array
    {
        $options['headers'] = ($options['headers'] ?? []) + ['Accept' => 'application/json'];
        $response = $this->send($method, $path, $options);

        return $response->toArray();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $path, array $options, bool $assert = true): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $this->environment()->baseUrl().$path, $options);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new KsefException('Brak połączenia z KSeF: '.$e->getMessage(), previous: $e);
        }

        if ($assert) {
            $this->assertSuccessful($response);
        }

        return $response;
    }

    private function assertSuccessful(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 300) {
            return;
        }

        if (429 === $status) {
            $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 60);
            throw new KsefRateLimitException(max(1, $retryAfter));
        }

        throw new KsefException(\sprintf('KSeF zwrócił błąd %d: %s', $status, $this->errorMessage($response)));
    }

    private function errorMessage(ResponseInterface $response): string
    {
        try {
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return mb_substr($response->getContent(false), 0, 300);
        }

        $details = $data['exception']['exceptionDetailList'] ?? [];
        if ([] !== $details) {
            return implode('; ', array_map(
                static fn (array $d): string => trim(($d['exceptionDescription'] ?? '').' '.implode(' ', (array) ($d['details'] ?? []))),
                $details,
            ));
        }

        return (string) ($data['detail'] ?? $data['title'] ?? 'nieznany błąd');
    }

    /**
     * @param array{access: string, accessUntil: int, refresh: string, refreshUntil: int} $tokens
     */
    private function store(array $tokens): void
    {
        $item = $this->cache->getItem($this->cacheKey());
        $item->set($tokens)->expiresAt((new \DateTimeImmutable())->setTimestamp($tokens['refreshUntil']));
        $this->cache->save($item);
    }

    private function forgetTokens(): void
    {
        $this->cache->deleteItem($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return 'ksef_tokens_'.hash('sha256', $this->environment()->value.'|'.$this->nip().'|'.$this->token());
    }

    private function environment(): KsefEnvironment
    {
        return KsefEnvironment::tryFrom((string) $this->settings->string(SettingKey::KSEF_ENVIRONMENT)) ?? KsefEnvironment::Test;
    }

    private function nip(): string
    {
        $nip = $this->identity->nip();
        if (null === $nip) {
            throw new KsefException('Uzupełnij NIP gospodarstwa w ustawieniach KSeF.');
        }

        return $nip;
    }

    private function token(): string
    {
        $encrypted = $this->settings->string(SettingKey::KSEF_TOKEN);
        if (null === $encrypted) {
            throw new KsefException('Brak tokenu KSeF. Wprowadź go w ustawieniach.');
        }

        return $this->secrets->decrypt($encrypted);
    }

    private static function isoDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
