<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class TrustedDeviceManager
{
    public const string COOKIE = 'folwark_device';

    public function __construct(
        private readonly TrustedDeviceRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function isValidPin(string $pin): bool
    {
        return 1 === preg_match('/^\d{4,6}$/', $pin);
    }

    /**
     * Rejestruje urządzenie i zwraca ciasteczko, które trzeba dołączyć do odpowiedzi.
     */
    public function trust(User $user, string $name, string $pin, Request $request): Cookie
    {
        if (!self::isValidPin($pin)) {
            throw new \InvalidArgumentException('PIN musi mieć od 4 do 6 cyfr.');
        }

        $current = $this->findForRequest($request);
        if (null !== $current) {
            $this->em->remove($current);
        }

        $token = bin2hex(random_bytes(32));
        $device = new TrustedDevice($user, $name, hash('sha256', $token), password_hash($pin, \PASSWORD_ARGON2ID));
        $this->em->persist($device);
        $this->em->flush();

        return Cookie::create(self::COOKIE, $token)
            ->withExpires(new \DateTimeImmutable('+1 year'))
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function findForRequest(Request $request): ?TrustedDevice
    {
        $token = $request->cookies->get(self::COOKIE);
        if (!\is_string($token) || '' === $token) {
            return null;
        }

        $device = $this->repository->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if (null === $device || $device->isLocked() || !$device->getUser()->isActive()) {
            return null;
        }

        return $device;
    }

    public function verifyPin(TrustedDevice $device, string $pin): bool
    {
        return password_verify($pin, $device->getPinHash());
    }

    public function revoke(TrustedDevice $device): void
    {
        $this->em->remove($device);
        $this->em->flush();
    }

    public static function clearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE, '', 1);
    }
}
