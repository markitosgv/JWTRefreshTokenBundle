<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use DateTimeInterface;
use Doctrine\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * @template T of RefreshTokenInterface
 *
 * @extends ObjectRepository<T>
 *
 * @psalm-mutable
 */
interface RefreshTokenRepositoryInterface extends ObjectRepository
{
    /**
     * `Doctrine\Persistence\ObjectRepository` does not take an order, but both the ORM and the ODM
     * repositories do, and it is needed to retrieve the last token of a user.
     *
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return T|null
     *
     * @psalm-impure it queries the storage
     */
    #[\Override]
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

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
