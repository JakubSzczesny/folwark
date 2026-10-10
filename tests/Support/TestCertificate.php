<?php

declare(strict_types=1);

namespace App\Tests\Support;

use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

/**
 * Samopodpisany certyfikat RSA do testów szyfrowania tokenu KSeF (bez zależności od openssl.cnf).
 */
final class TestCertificate
{
    /**
     * @return array{private: RSA\PrivateKey, certificateBase64: string}
     */
    public static function create(): array
    {
        $private = RSA::createKey(2048);
        \assert($private instanceof RSA\PrivateKey);

        $subject = new X509();
        $subject->setDN('CN=KSeF test');
        $subject->setPublicKey($private->getPublicKey());

        $issuer = new X509();
        $issuer->setPrivateKey($private->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256'));
        $issuer->setDN('CN=KSeF test');

        $x509 = new X509();
        $certificate = $x509->sign($issuer, $subject);
        $pem = $x509->saveX509($certificate);

        return [
            'private' => $private,
            'certificateBase64' => str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $pem),
        ];
    }
}
