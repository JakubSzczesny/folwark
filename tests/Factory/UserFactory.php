<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\User;
use App\Enum\UserRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    public static function owner(?Farm $farm = null): User
    {
        return self::member(UserRole::Owner, $farm);
    }

    public static function worker(?Farm $farm = null): User
    {
        return self::member(UserRole::Worker, $farm);
    }

    public static function accountant(?Farm $farm = null): User
    {
        return self::member(UserRole::Accountant, $farm);
    }

    /**
     * Użytkownik z rolą w gospodarstwie (domyślnie w gospodarstwie testowym).
     *
     * @param array<string, mixed> $attributes
     */
    public static function member(UserRole $role, ?Farm $farm = null, array $attributes = []): User
    {
        $user = self::createOne($attributes);
        persist(FarmMembership::class, ['farm' => $farm ?? FarmFactory::testFarm(), 'user' => $user, 'role' => $role]);

        return $user;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'fullName' => self::faker()->name(),
            'password' => 'nie-uzywane-w-testach',
            'active' => true,
        ];
    }
}
