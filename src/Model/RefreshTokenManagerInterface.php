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
 */
interface RefreshTokenManagerInterface
{
    public const MAX_BATCH_SIZE = 1000;

    public function get(string $refreshToken): ?RefreshTokenInterface;

    public function getLastFromUsername(string $username): ?RefreshTokenInterface;

    public function save(RefreshTokenInterface $refreshToken): void;

    public function delete(RefreshTokenInterface $refreshToken): void;

    /**
     * Revokes all invalid (expired) refresh tokens.
     * 
     * @param DateTimeInterface|null $datetime The date and time to consider for invalidation.
     * @param bool $andFlush Whether to flush the object manager after revoking.
     * 
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array;

    /**
     * Revokes all invalid refresh tokens in batches.
     *
     * @param DateTimeInterface|null $datetime The date and time to consider for invalidation.
     * @param int|null $batchSize The number of tokens to process in each batch.
     *                            Defaults to self::MAX_BATCH_SIZE if not provided.
     * @param int|null $offset The offset to start processing from.
     *                            Defaults to 0 if not provided.
     * @param bool $andFlush Whether to flush the object manager after revoking.
     *
     * @return RefreshTokenInterface[] An array of revoked refresh tokens.
     */
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = self::MAX_BATCH_SIZE, ?int $offset = 0, bool $andFlush = true): array;

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     */
    public function getClass(): string;
}
