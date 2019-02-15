<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\ORM\EntityRepository;

class RefreshTokenRepository extends EntityRepository
{
    /**
     * @param \DateTime|null $datetime
     *
     * @return RefreshToken[]
     */
    public function findInvalid($datetime = null)
    {
        $datetime = (null === $datetime) ? new \DateTime() : $datetime;

        return $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime)
            ->getQuery()
            ->getResult();
    }
}
