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
     * Revokes every refresh token issued to the given user.
     *
     * @return int the number of revoked refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeAllForUser(UserInterface $user): int;
}
