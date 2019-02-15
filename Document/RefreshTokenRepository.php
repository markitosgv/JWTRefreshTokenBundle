<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;

class RefreshTokenRepository extends DocumentRepository
{
    /**
     * @param \DateTime|null $datetime
     *
     * @return RefreshToken[]
     */
    public function findInvalid($datetime = null)
    {
        $datetime = (null === $datetime) ? new \DateTime() : $datetime;

        $queryBuilder = $this->createQueryBuilder()
            ->field('valid')->lt($datetime);

        return $queryBuilder->getQuery()->execute();
    }
}
