<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use App\Setup\StartupData;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dane demonstracyjne do pracy lokalnej. Hasło wszystkich kont: "folwark-demo".
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'folwark-demo';

    public function __construct(
        private readonly StartupData $startupData,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->startupData->seed();

        foreach ([
            ['wlasciciel@folwark.test', 'Jan Kowalski', UserRole::Owner],
            ['pracownik@folwark.test', 'Piotr Nowak', UserRole::Worker],
            ['ksiegowa@folwark.test', 'Anna Wiśniewska', UserRole::Accountant],
        ] as [$email, $name, $role]) {
            $user = (new User())->setEmail($email)->setFullName($name)->setRole($role);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
            $this->addReference('user-'.$role->value, $user);
        }

        $manager->flush();
    }
}
