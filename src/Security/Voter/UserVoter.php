<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserVoter extends Voter
{
    const string VIEW = 'USER_VIEW';
    const string LIST = 'USER_LIST';
    const string CREATE = 'USER_CREATE';
    const string EDIT = 'USER_EDIT';
    const string DELETE = 'USER_DELETE';

    public function __construct(private readonly Security $security)
    {
    }


    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::LIST === $attribute) {
            return true;
        }

        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE]) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (self::CREATE === $attribute && !$user) {
            return true;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (!$user instanceof UserInterface) {
            return false;
        }

        return $user === $subject;
    }
}
