<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use DateTimeInterface;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends EntityRepository<RefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
class RefreshTokenRepository extends EntityRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @return iterable<RefreshToken>
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime ?? new DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds a batch of invalid (expired) refresh tokens.
     * This method is useful for processing large datasets in manageable chunks.
     *
     * @param DateTimeInterface|null $datetime  The date and time to consider for invalidation.
     *                                          If null, the current date and time will be used.
     * @param int                    $batchSize The number of tokens to process in each batch.
     *                                          This should be a positive integer, typically set to a value like 1000.
     * @param int                    $offset    The offset to start processing from.
     *                                          This allows for pagination through the results, starting from the specified offset.
     *                                          It should be a non-negative integer, typically starting from 0.
     *
     * @return iterable<RefreshToken>
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        return $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime ?? new DateTime())
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();
    }
}
