<?php

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Revokes every refresh token issued to a user, for a password reset or an account being disabled.
 *
 * Kept apart from RefreshTokenManagerInterface so that an existing implementation of it keeps
 * working without this method.
 *
 * @psalm-mutable
 */
interface RevokeRefreshTokenManagerInterface
{
    /**
     * @psalm-impure it writes to the storage
     */
    public function revokeAllForUser(UserInterface $user): void;
}
