<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use DateTimeInterface;

/**
 * Interface to be implemented by user managers. This adds an additional level
 * of abstraction between your application, and the actual repository.
 *
 * All changes to RefreshTokenInterface objects should happen through this interface.
 *
 * @psalm-mutable
 */
interface RefreshTokenManagerInterface
{
    public const DEFAULT_BATCH_SIZE = 1000;

    /**
     * @psalm-impure it queries the storage
     */
    public function get(string $refreshToken): ?RefreshTokenInterface;

    /**
     * @psalm-impure it queries the storage
     */
    public function getLastFromUsername(string $username): ?RefreshTokenInterface;

    /**
     * @psalm-impure it writes to the storage
     */
    public function save(RefreshTokenInterface $refreshToken): void;

    /**
     * Deletes the given refresh token and returns the number of rows affected.
     *
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     *
     * @psalm-impure it writes to the storage
     */
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int;

    /**
     * Revokes all invalid (expired) refresh tokens.
     *
     * @param DateTimeInterface|null $datetime the date and time to consider for invalidation
     * @param bool                   $andFlush whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array;

    /**
     * Revokes all invalid refresh tokens in batches.
     *
     * @param DateTimeInterface|null $datetime  the date and time to consider for invalidation
     * @param positive-int|null      $batchSize The number of tokens to process in each batch.
     *                                          Defaults to self::DEFAULT_BATCH_SIZE if not provided.
     * @param int<0, max>            $offset    The offset to start processing from.
     *                                          Defaults to 0 if not provided.
     * @param bool                   $andFlush  whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[] an array of revoked refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array;

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     *
     * @psalm-mutation-free
     */
    public function getClass(): string;
}
