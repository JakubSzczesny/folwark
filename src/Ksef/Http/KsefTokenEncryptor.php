<?php

declare(strict_types=1);

namespace App\Ksef\Http;

use App\Ksef\KsefException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Szyfruje „token|timestampMs” kluczem publicznym MF: RSA-OAEP z SHA-256 (także MGF1).
 * openssl_public_encrypt w PHP < 8.5 obsługuje OAEP tylko z SHA-1, stąd phpseclib.
 */
final class KsefTokenEncryptor
{
    public function encrypt(string $token, int $timestampMs, string $certificateBase64): string
    {
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split($certificateBase64, 64, "\n")."-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($pem);
        if (false === $publicKey) {
            throw new KsefException('Nie udało się odczytać certyfikatu klucza publicznego KSeF.');
        }

        $details = openssl_pkey_get_details($publicKey);
        if (false === $details || !isset($details['key'])) {
            throw new KsefException('Certyfikat KSeF nie zawiera klucza publicznego.');
        }

        $key = PublicKeyLoader::loadPublicKey($details['key']);
        if (!$key instanceof RSA\PublicKey) {
            throw new KsefException('Klucz publiczny KSeF nie jest kluczem RSA.');
        }

        $cipher = $key->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256');

        return base64_encode($cipher->encrypt($token.'|'.$timestampMs));
    }
}
