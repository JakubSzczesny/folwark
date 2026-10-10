<?php

declare(strict_types=1);

namespace App\Settings;

use Symfony\Component\Form\FormInterface;

/**
 * Przenosi wartości między formularzem a ustawieniami. Nazwa pola formularza
 * to klucz ustawienia z kropkami zamienionymi na "__".
 */
final class SettingsForm
{
    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function data(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[self::field($key)] = $this->settings->get($key);
        }

        return $data;
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     * @param list<string>                        $keys
     */
    public function save(FormInterface $form, array $keys): void
    {
        foreach ($keys as $key) {
            $this->settings->set($key, $form->get(self::field($key))->getData(), false);
        }
    }
}
