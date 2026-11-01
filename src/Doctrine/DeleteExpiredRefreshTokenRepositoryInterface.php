<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use DateTimeInterface;

/**
 * Deletes every expired refresh token in one query.
 *
 * Kept apart from DeleteRefreshTokenRepositoryInterface so that a repository written against that
 * one keeps working. A repository of your own gains direct clearing by implementing this.
 *
 * @psalm-mutable
 */
interface DeleteExpiredRefreshTokenRepositoryInterface
{
    /**
     * Deletes every refresh token that had expired by the given moment, now when none is given.
     *
     * Life-cycle events might not be triggered for the deleted tokens.
     *
     * @return int the amount of deleted refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function deleteAllExpired(?DateTimeInterface $datetime = null): int;
}
