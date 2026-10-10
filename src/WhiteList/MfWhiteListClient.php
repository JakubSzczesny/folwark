<?php

declare(strict_types=1);

namespace App\WhiteList;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Publiczne API Wykazu podatników VAT: GET https://wl-api.mf.gov.pl/api/search/nip/{nip}?date=RRRR-MM-DD.
 * Wyniki trzymamy w cache, a liczbę zapytań ogranicza dzienny limit (MF limituje zapytania „search”).
 */
final class MfWhiteListClient implements WhiteListClient
{
    private const string BASE_URL = 'https://wl-api.mf.gov.pl';
    private const int CACHE_SECONDS = 7 * 86400;

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(service: 'limiter.white_list')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function findByNip(string $nip, \DateTimeImmutable $date): ?WhiteListSubject
    {
        $item = $this->cache->getItem('white_list_'.$nip);
        if ($item->isHit()) {
            $cached = $item->get();

            return \is_array($cached) ? WhiteListSubject::fromApi($cached) : null;
        }

        if (!$this->limiter->create('mf')->consume()->isAccepted()) {
            throw new WhiteListException('Wykorzystano dzienny limit zapytań do Białej listy. Spróbuj jutro.');
        }

        try {
            $response = $this->http->request('GET', self::BASE_URL.'/api/search/nip/'.$nip, [
                'query' => ['date' => $date->format('Y-m-d')],
                'headers' => ['Accept' => 'application/json'],
            ]);
            $data = $response->toArray(false);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new WhiteListException('Brak połączenia z Białą listą: '.$e->getMessage(), previous: $e);
        }

        if ($status >= 400) {
            throw new WhiteListException(\sprintf('Biała lista zwróciła błąd %s: %s', $data['code'] ?? $status, $data['message'] ?? ''));
        }

        $subject = $data['result']['subject'] ?? null;
        $item->set(\is_array($subject) ? $subject : false)->expiresAfter(self::CACHE_SECONDS);
        $this->cache->save($item);

        return \is_array($subject) ? WhiteListSubject::fromApi($subject) : null;
    }
}
