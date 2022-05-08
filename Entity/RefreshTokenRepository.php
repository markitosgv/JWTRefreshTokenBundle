<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

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
     * @return RefreshToken[]
     */
    public function findInvalid(?\DateTimeInterface $datetime = null): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime ?? new \DateTime())
            ->getQuery()
            ->getResult();
    }
}
