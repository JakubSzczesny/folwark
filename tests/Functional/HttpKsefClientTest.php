<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Audit\AuditLogger;
use App\Crypto\SecretBox;
use App\Farm\FarmContext;
use App\Ksef\Dto\MetadataQuery;
use App\Ksef\Http\HttpKsefClient;
use App\Ksef\Http\KsefTokenEncryptor;
use App\Ksef\KsefIdentity;
use App\Ksef\KsefRateLimitException;
use App\Ksef\SubjectType;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\FarmFactory;
use App\Tests\Support\TestCertificate;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Przebieg uwierzytelnienia tokenem i zapytania o metadane wg dokumentacji API KSeF 2.0 – na atrapie HTTP.
 */
final class HttpKsefClientTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /** @var list<array{method: string, url: string, body: ?string, auth: ?string}> */
    private array $requests = [];

    public function testAuthenticatesWithTokenAndQueriesMetadata(): void
    {
        $client = $this->client([
            new MockResponse(self::json(['challenge' => '20260101-CR-1111111111-2222222222-33', 'timestamp' => '2026-01-01T10:00:00.123Z', 'timestampMs' => 1767261600123, 'clientIp' => '127.0.0.1'])),
            new MockResponse(self::json([[
                'certificate' => $this->certificate(),
                'certificateId' => 'Y2VydA==',
                'publicKeyId' => str_repeat('A', 44),
                'validFrom' => '2025-01-01T00:00:00Z',
                'validTo' => '2030-01-01T00:00:00Z',
                'usage' => ['KsefTokenEncryption'],
            ]])),
            new MockResponse(self::json(['referenceNumber' => '20260101-AU-1111111111-2222222222-33', 'authenticationToken' => ['token' => 'auth-jwt', 'validUntil' => '2026-01-01T11:00:00Z']]), ['http_code' => 202]),
            new MockResponse(self::json(['status' => ['code' => 100, 'description' => 'Uwierzytelnianie w toku']])),
            new MockResponse(self::json(['status' => ['code' => 200, 'description' => 'Uwierzytelnianie zakończone sukcesem']])),
            new MockResponse(self::json([
                'accessToken' => ['token' => 'access-jwt', 'validUntil' => '2099-01-01T00:00:00Z'],
                'refreshToken' => ['token' => 'refresh-jwt', 'validUntil' => '2099-01-07T00:00:00Z'],
            ])),
            new MockResponse(self::json([
                'hasMore' => false,
                'isTruncated' => false,
                'permanentStorageHwmDate' => '2026-05-05T00:00:00Z',
                'invoices' => [[
                    'ksefNumber' => '7311000000-20260504-AAAAAA-BBBBBB-CC',
                    'invoiceNumber' => 'PET/1/2026',
                    'issueDate' => '2026-05-04',
                    'invoicingDate' => '2026-05-04T10:00:00Z',
                    'acquisitionDate' => '2026-05-04T10:00:01Z',
                    'permanentStorageDate' => '2026-05-04T10:00:02Z',
                    'seller' => ['nip' => '7311000000', 'name' => 'PETROMEX'],
                    'buyer' => ['identifier' => ['type' => 'Nip', 'value' => '5841000001'], 'name' => 'Gospodarstwo'],
                    'netAmount' => 1000, 'grossAmount' => 1230, 'vatAmount' => 230, 'currency' => 'PLN',
                    'invoicingMode' => 'Online', 'invoiceType' => 'Vat',
                    'formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
                    'isSelfInvoicing' => false, 'hasAttachment' => false, 'invoiceHash' => str_repeat('A', 44),
                ]],
            ])),
        ]);

        $page = $client->queryMetadata(new MetadataQuery(SubjectType::Buyer, new \DateTimeImmutable('2026-05-01T00:00:00Z')));

        self::assertCount(1, $page->invoices);
        self::assertSame('PET/1/2026', $page->invoices[0]->invoiceNumber);
        self::assertSame('1230.00', $page->invoices[0]->grossAmount);
        self::assertSame('5841000001', $page->invoices[0]->buyerIdentifier);
        self::assertFalse($page->hasMore);

        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/auth/challenge', $this->requests[0]['url']);
        self::assertStringEndsWith('/security/public-key-certificates', $this->requests[1]['url']);
        $init = json_decode((string) $this->requests[2]['body'], true);
        self::assertSame(['type' => 'Nip', 'value' => '5841000001'], $init['contextIdentifier']);
        self::assertSame('20260101-CR-1111111111-2222222222-33', $init['challenge']);
        self::assertNotEmpty($init['encryptedToken']);
        self::assertSame('Bearer auth-jwt', $this->requests[3]['auth']);
        self::assertStringEndsWith('/auth/token/redeem', $this->requests[5]['url']);

        $query = $this->requests[6];
        self::assertStringContainsString('/invoices/query/metadata?sortOrder=Asc&pageOffset=0&pageSize=250', $query['url']);
        self::assertSame('Bearer access-jwt', $query['auth']);
        $body = json_decode((string) $query['body'], true);
        self::assertSame('Subject2', $body['subjectType']);
        self::assertSame('PermanentStorage', $body['dateRange']['dateType']);
        self::assertSame('2026-05-01T00:00:00.000Z', $body['dateRange']['from']);
        self::assertArrayNotHasKey('to', $body['dateRange']);
    }

    public function testRateLimitIsReported(): void
    {
        $client = $this->client([
            new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['Retry-After' => '120']]),
        ]);

        try {
            $client->testConnection();
            self::fail('Oczekiwano wyjątku limitu.');
        } catch (KsefRateLimitException $e) {
            self::assertSame(120, $e->retryAfterSeconds);
        }
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): HttpKsefClient
    {
        self::bootKernel();
        $container = static::getContainer();

        $container->get(FarmContext::class)->set(FarmFactory::testFarm());
        $settings = $container->get(Settings::class);
        $settings->set(SettingKey::KSEF_TOKEN, $container->get(SecretBox::class)->encrypt('token-testowy'));

        $index = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$index, $responses): MockResponse {
            $auth = null;
            foreach ($options['normalized_headers']['authorization'] ?? [] as $header) {
                $auth = substr($header, \strlen('Authorization: '));
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : null, 'auth' => $auth];

            return $responses[$index++] ?? throw new \LogicException('Brak przygotowanej odpowiedzi dla '.$url);
        });

        return new HttpKsefClient(
            $http,
            $settings,
            $container->get(KsefIdentity::class),
            $container->get(SecretBox::class),
            new KsefTokenEncryptor(),
            new ArrayAdapter(),
            $container->get(AuditLogger::class),
            new NullLogger(),
            0,
        );
    }

    /**
     * @param array<mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }

    private function certificate(): string
    {
        return TestCertificate::create()['certificateBase64'];
    }
}
