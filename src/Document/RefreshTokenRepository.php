<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use DateTimeInterface;
use DateTime;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends DocumentRepository<RefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
class RefreshTokenRepository extends DocumentRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @return iterable<RefreshToken>
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * @param int $batchSize
     * @param int $offset
     * @return iterable<RefreshToken>
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, int $batchSize, int $offset): iterable
    {
        return $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new DateTime())
            ->skip($offset)
            ->limit($batchSize)
            ->getQuery()
            ->execute();
    }
}
