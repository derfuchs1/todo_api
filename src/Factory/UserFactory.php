<?php

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->email(),
            'plainPassword' => self::faker()->password(),
            'roles' => [],
        ];
    }

    protected function initialize(): static
    {
        return $this->afterPersist(function (User $user, array $attributes) {
            $user->setPlainPassword($attributes['plainPassword']);
        });
    }
}
