<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

/**
 * Deletes a whole chain of refreshes in one query.
 *
 * Kept apart from DeleteRefreshTokenRepositoryInterface so that a repository written against that
 * one keeps working. A repository of your own gains family revocation by implementing this.
 *
 * @psalm-mutable
 */
interface DeleteRefreshTokenFamilyRepositoryInterface
{
    /**
     * Deletes every refresh token belonging to the given chain.
     *
     * Life-cycle events might not be triggered for the deleted tokens.
     *
     * @return int the amount of deleted refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function deleteByFamily(string $family): int;
}
