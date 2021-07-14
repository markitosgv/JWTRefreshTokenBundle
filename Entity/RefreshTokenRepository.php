<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

class RefreshTokenRepository extends EntityRepository
{
    /**
     * @param \DateTimeInterface|null $datetime
     *
     * @return RefreshTokenInterface[]
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
