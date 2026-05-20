<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RoleVoter extends Voter
{
    private const SUPPORTED_ROLES = [
        'ROLE_ADMIN',
        'ROLE_SUPERVISOR',
        'ROLE_STUDENT',
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ROLES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            'ROLE_ADMIN' => $user->getRole() === Role::Admin,
            'ROLE_SUPERVISOR' => $user->getRole() === Role::Supervisor || $user->getRole() === Role::Admin,
            'ROLE_STUDENT' => true,
            default => false,
        };
    }
}
