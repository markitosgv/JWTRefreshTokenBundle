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
     * @return iterable<RefreshToken>
     */
    public function findInvalid(?\DateTimeInterface $datetime = null): iterable
    {
        return $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new \DateTime())
            ->getQuery()
            ->execute();
    }
}
