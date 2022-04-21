<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

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
     * @param \DateTimeInterface|null $datetime
     *
     * @return RefreshToken[]
     */
    public function findInvalid($datetime = null)
    {
        return $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new \DateTime())
            ->getQuery()
            ->execute();
    }
}
