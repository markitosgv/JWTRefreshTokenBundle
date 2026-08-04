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

    /**
     * Revokes the refresh tokens of a user beyond the newest $keep of them.
     *
     * Newest is by expiry rather than by creation, so with `ttl_update` on it is the sessions that
     * have gone longest without being refreshed that go first. Tokens that have already expired sort
     * to the end and are revoked before any that are still usable.
     *
     * @param positive-int $keep how many of the user's tokens to leave in place
     *
     * @return int the number of revoked refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int;
}
