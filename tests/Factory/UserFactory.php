<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use App\Enum\UserRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    public static function owner(): User
    {
        return self::createOne(['role' => UserRole::Owner]);
    }

    public static function worker(): User
    {
        return self::createOne(['role' => UserRole::Worker]);
    }

    public static function accountant(): User
    {
        return self::createOne(['role' => UserRole::Accountant]);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'fullName' => self::faker()->name(),
            'role' => UserRole::Owner,
            'password' => 'nie-uzywane-w-testach',
            'active' => true,
        ];
    }
}
