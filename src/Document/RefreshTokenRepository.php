<?php

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use DateTimeInterface;
use DateTime;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DeleteRefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use MongoDB\DeleteResult;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends DocumentRepository<RefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
class RefreshTokenRepository extends DocumentRepository implements RefreshTokenRepositoryInterface, DeleteRefreshTokenRepositoryInterface
{
    /**
     * @return iterable<RefreshToken>
     */
    #[\Override]
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime ?? new DateTime())
            ->getQuery()
            ->getIterator();
    }

    /**
     * @return iterable<RefreshToken>
     */
    #[\Override]
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

        return $qb->getQuery()->getIterator();
    }

    #[\Override]
    public function deleteByUser(UserInterface $user): int
    {
        /** @var DeleteResult */
        $result = $this->createQueryBuilder()
            ->field('username')
            ->equals($user->getUserIdentifier())
            ->remove()
            ->getQuery()
            ->execute();

        return $result->isAcknowledged() ? $result->getDeletedCount() : 0;
    }

    #[\Override]
    public function deleteAllButNewestForUser(UserInterface $user, int $keep): int
    {
        // The ids to remove are read first: the ones to keep are the newest, which is a skip away
        // from the front, and a remove takes no skip
        /** @var iterable<RefreshToken> $stale */
        $stale = $this->createQueryBuilder()
            ->field('username')
            ->equals($user->getUserIdentifier())
            ->sort('valid', 'desc')
            ->skip($keep)
            ->getQuery()
            ->getIterator();

        $ids = [];

        foreach ($stale as $token) {
            $ids[] = $token->getId();
        }

        if ([] === $ids) {
            return 0;
        }

        /** @var DeleteResult $result */
        $result = $this->createQueryBuilder()
            ->field('id')
            ->in($ids)
            ->remove()
            ->getQuery()
            ->execute();

        return $result->isAcknowledged() ? $result->getDeletedCount() : 0;
    }

    public function deleteAllExpired(DateTimeInterface $datetime = new DateTime()): int
    {
        /** @var DeleteResult $result */
        $result = $this->createQueryBuilder()
            ->field('valid')
            ->lt($datetime)
            ->remove()
            ->getQuery()
            ->execute();

        return $result->isAcknowledged() ? $result->getDeletedCount() : 0;
    }

    #[\Override]
    public function deleteToken(RefreshTokenInterface $refreshToken): int
    {
        /** @var DeleteResult $result */
        $result = $this->createQueryBuilder()
            ->field('id')
            ->equals($refreshToken->getId())
            ->remove()
            ->getQuery()
            ->execute();

        return $result->isAcknowledged() ? $result->getDeletedCount() : 0;
    }
}
