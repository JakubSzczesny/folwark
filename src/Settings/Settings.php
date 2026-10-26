<?php

declare(strict_types=1);

namespace App\Settings;

use App\Audit\AuditLogger;
use App\Entity\Setting;
use App\Farm\FarmContext;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Ustawienia bieżącego gospodarstwa z wartościami domyślnymi.
 */
class Settings implements ResetInterface
{
    /** @var array<int, array<string, Setting>> id gospodarstwa => ustawienia */
    private array $loaded = [];

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
        private readonly FarmContext $context,
    ) {
    }

    public function get(string $key): mixed
    {
        if (!\array_key_exists($key, SettingKey::DEFAULTS)) {
            throw new \InvalidArgumentException(\sprintf('Nieznane ustawienie "%s".', $key));
        }

        $settings = $this->load();

        return isset($settings[$key]) ? $settings[$key]->getValue() : SettingKey::DEFAULTS[$key];
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
        $farmId = $this->context->farmId() ?? throw new \LogicException('Ustawienia zapisuje się w kontekście gospodarstwa.');
        $settings = $this->load();
        $setting = $settings[$key] ?? null;
        $old = $setting?->getValue() ?? (SettingKey::DEFAULTS[$key] ?? null);

        if (null === $setting) {
            $setting = new Setting($key);
            $this->em->persist($setting);
            $this->loaded[$farmId][$key] = $setting;
        }
        $setting->setValue($value);

        if ($old !== $value && !\in_array($key, SettingKey::INTERNAL, true)) {
            $secret = \in_array($key, SettingKey::SECRET, true);
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
        $this->loaded = [];
    }

    /**
     * @return array<string, Setting>
     */
    private function load(): array
    {
        $farmId = $this->context->farmId();
        if (null === $farmId) {
            return [];
        }

        if (!isset($this->loaded[$farmId])) {
            $this->loaded[$farmId] = [];
            foreach ($this->repository->findAll() as $setting) {
                $this->loaded[$farmId][$setting->getName()] = $setting;
            }
        }

        return $this->loaded[$farmId];
    }
}
