<?php

namespace App\EntityListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
readonly class UserEntityListener
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    public function preUpdate(User $user): void
    {
        $this->hashPassword($user);
    }

    public function prePersist(User $user): void
    {
        $this->hashPassword($user);
    }

    private function hashPassword(User $user): void {
        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword) {
            return;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();
    }
}
