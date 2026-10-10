<?php

declare(strict_types=1);

namespace App\Settings;

use App\Audit\AuditLogger;
use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

class Settings implements ResetInterface
{
    /** @var array<string, Setting>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    public function get(string $key): mixed
    {
        $this->load();
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key]->getValue();
        }

        if (!\array_key_exists($key, SettingKey::DEFAULTS)) {
            throw new \InvalidArgumentException(\sprintf('Nieznane ustawienie "%s".', $key));
        }

        return SettingKey::DEFAULTS[$key];
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return null === $value || '' === $value ? null : (string) $value;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * @return list<int>
     */
    public function intList(string $key): array
    {
        $value = $this->get($key);
        if (!\is_array($value)) {
            return [];
        }

        $list = array_values(array_unique(array_map('intval', $value)));
        rsort($list);

        return $list;
    }

    public function date(string $key): ?\DateTimeImmutable
    {
        $value = $this->string($key);

        return null === $value ? null : new \DateTimeImmutable($value);
    }

    public function set(string $key, mixed $value, bool $flush = true): void
    {
        $this->load();
        $setting = $this->loaded[$key] ?? null;
        $old = $setting?->getValue() ?? (SettingKey::DEFAULTS[$key] ?? null);

        if (null === $setting) {
            $setting = new Setting($key);
            $this->em->persist($setting);
            $this->loaded[$key] = $setting;
        }
        $setting->setValue($value);

        if ($old !== $value && !\in_array($key, SettingKey::INTERNAL, true)) {
            $secret = SettingKey::KSEF_TOKEN === $key;
            $this->audit->log('Setting', $key, 'change', [
                'old' => $secret ? '***' : $old,
                'new' => $secret ? '***' : $value,
            ]);
        }

        if ($flush) {
            $this->em->flush();
        }
    }

    public function reset(): void
    {
        $this->loaded = null;
    }

    private function load(): void
    {
        if (null !== $this->loaded) {
            return;
        }

        $this->loaded = [];
        foreach ($this->repository->findAll() as $setting) {
            $this->loaded[$setting->getName()] = $setting;
        }
    }
}
