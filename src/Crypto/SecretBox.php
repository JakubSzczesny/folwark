<?php

declare(strict_types=1);

namespace App\Crypto;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SecretBox
{
    public function __construct(
        #[Autowire('%env(ENCRYPTION_KEY)%')]
        private readonly string $secret,
    ) {
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Nieprawidłowy zaszyfrowany sekret.');
        }

        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());
        if (false === $plain) {
            throw new \RuntimeException('Nie udało się odszyfrować sekretu – sprawdź ENCRYPTION_KEY.');
        }

        return $plain;
    }

    private function key(): string
    {
        if ('' === $this->secret) {
            throw new \RuntimeException('Brak klucza ENCRYPTION_KEY (ustaw go przez bin/console secrets:set).');
        }

        return sodium_crypto_generichash($this->secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
