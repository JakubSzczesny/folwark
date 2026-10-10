<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ksef;

use App\Ksef\Http\KsefTokenEncryptor;
use App\Tests\Support\TestCertificate;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\TestCase;

final class KsefTokenEncryptorTest extends TestCase
{
    public function testEncryptsTokenWithTimestampUsingOaepSha256(): void
    {
        ['private' => $private, 'certificateBase64' => $certificate] = TestCertificate::create();

        $encrypted = (new KsefTokenEncryptor())->encrypt('moj-token', 1767225600123, $certificate);

        $decrypted = $private->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')->decrypt((string) base64_decode($encrypted, true));

        self::assertSame('moj-token|1767225600123', $decrypted);
    }
}
