<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository as MongoDBDocumentRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

if (class_exists(MongoDBDocumentRepository::class)) {
    /**
     * Internal repository supporting doctrine/mongodb-odm >=2.0.
     *
     * @template T of object
     * @extends MongoDBDocumentRepository<T>
     *
     * @internal
     */
    class BaseRepository extends MongoDBDocumentRepository
    {
    }
} else {
    /**
     * Internal repository supporting doctrine/mongodb-odm <2.0.
     *
     * @internal
     */
    class BaseRepository extends DocumentRepository
    {
    }
}

/**
 * @extends BaseRepository<RefreshToken>
 */
class RefreshTokenRepository extends BaseRepository
{
    /**
     * @param \DateTimeInterface|null $datetime
     *
     * @return RefreshTokenInterface[]
     */
    public function findInvalid($datetime = null)
    {
        $datetime = (null === $datetime) ? new \DateTime() : $datetime;

        $queryBuilder = $this->createQueryBuilder()
            ->field('valid')->lt($datetime);

        return $queryBuilder->getQuery()->execute();
    }
}
