<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @psalm-mutable
 */
interface DeleteRefreshTokenRepositoryInterface
{
    /**
     * Deletes all refresh tokens issued to the given user.
     *
     * Life-cycle events might not be triggered for the deleted tokens.
     *
     * @param UserInterface $user the user whose refresh tokens are to be deleted
     *
     * @return int the amount of deleted refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function deleteByUser(UserInterface $user): int;
}
