<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
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

    /**
     * Deletes the given refresh token and reports what the storage actually deleted.
     *
     * Reading the token back first and reporting one row cannot tell two concurrent callers apart:
     * both find it, both ask for it to be removed, and both are told they succeeded, while only one
     * of the deletes reached a row. That distinction is what tells a single use token it was used.
     *
     * Life-cycle events might not be triggered for the deleted token.
     *
     * @return int the amount of deleted refresh tokens, 0 when it was already gone
     *
     * @psalm-impure it writes to the storage
     */
    public function deleteToken(RefreshTokenInterface $refreshToken): int;

    /**
     * Deletes the refresh tokens of a user beyond the newest $keep of them, by expiry.
     *
     * Life-cycle events might not be triggered for the deleted tokens.
     *
     * @param positive-int $keep
     *
     * @return int the amount of deleted refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function deleteAllButNewestForUser(UserInterface $user, int $keep): int;
}
