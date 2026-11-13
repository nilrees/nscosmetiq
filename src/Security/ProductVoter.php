<?php

namespace App\Security;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProductVoter extends Voter
{
    protected function supports(
        string $attribute,
        mixed $subject
    ): bool {
        return in_array(
            $attribute,
            ['edit', 'delete'],
            true
        ) && $subject instanceof Product;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin can edit and delete ALL products.
        if ($user->isAdmin()) {
            return true;
        }

        // Only staff members can manage their own products.
        if (!in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return false;
        }

        // Staff can edit/delete ONLY products they created.
        $owner = $subject->getCreatedBy();

        return $owner !== null
            && $owner->getId() !== null
            && $owner->getId() === $user->getId();
    }
}
