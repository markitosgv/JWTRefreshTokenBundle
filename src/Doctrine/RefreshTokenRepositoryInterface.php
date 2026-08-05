<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use DateTimeInterface;
use Doctrine\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * `Doctrine\Persistence\ObjectRepository` declares findOneBy() without an order, but both the ORM
 * and the ODM repositories accept one, and it is needed to retrieve the last token of a user. It is
 * described here rather than declared, so that an implementation is free to keep the inherited
 * signature.
 *
 * @template T of RefreshTokenInterface
 *
 * @extends ObjectRepository<T>
 *
 * @method T|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 *
 * @psalm-mutable
 */
interface RefreshTokenRepositoryInterface extends ObjectRepository
{
    /**
     * @return iterable<T>
     *
     * @psalm-impure it queries the storage
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable;

    /**
     * @param positive-int|null $batchSize
     * @param int<0, max>       $offset
     *
     * @return iterable<T>
     *
     * @psalm-impure it queries the storage
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable;
}
