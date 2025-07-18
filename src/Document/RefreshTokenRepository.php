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
     * @return iterable<RefreshToken>
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        $qb = $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new DateTime());

        if (null !== $batchSize) {
            $qb->limit($batchSize);
        }

        if ($offset > 0) {
            $qb->skip($offset);
        }

        return $qb->getQuery()->execute();
    }
}
