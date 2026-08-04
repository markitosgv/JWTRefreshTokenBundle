<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use DateTimeInterface;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DeleteRefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends EntityRepository<RefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
class RefreshTokenRepository extends EntityRepository implements RefreshTokenRepositoryInterface, DeleteRefreshTokenRepositoryInterface
{
    /**
     * @return iterable<RefreshToken>
     */
    #[\Override]
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        /** @var list<RefreshToken> $tokens */
        $tokens = $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime ?? new DateTime())
            ->getQuery()
            ->getResult();

        return $tokens;
    }

    /**
     * Finds a batch of invalid (expired) refresh tokens.
     * This method is useful for processing large datasets in manageable chunks.
     *
     * @param DateTimeInterface|null $datetime  The date and time to consider for invalidation.
     *                                          If null, the current date and time will be used.
     * @param int                    $batchSize The number of tokens to process in each batch.
     *                                          This should be a positive integer, typically set to a value like 1000.
     * @param int                    $offset    The offset to start processing from.
     *                                          This allows for pagination through the results, starting from the specified offset.
     *                                          It should be a non-negative integer, typically starting from 0.
     *
     * @return iterable<RefreshToken>
     */
    #[\Override]
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        /** @var list<RefreshToken> $tokens */
        $tokens = $this->createQueryBuilder('u')
            ->where('u.valid < :datetime')
            ->setParameter(':datetime', $datetime ?? new DateTime())
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();

        return $tokens;
    }

    #[\Override]
    public function deleteByUser(UserInterface $user): int
    {
        /** @var int $deleted */
        $deleted = $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.username = :user_identifier')
            ->setParameter('user_identifier', $user->getUserIdentifier(), ParameterType::STRING)
            ->getQuery()
            ->execute();

        return $deleted;
    }

    #[\Override]
    public function deleteAllButNewestForUser(UserInterface $user, int $keep): int
    {
        // The rows to delete are found first: an offset cannot be put on a DELETE, and the ones to
        // keep are the newest, which is an offset away from the front
        /** @var list<RefreshToken> $stale */
        $stale = $this->createQueryBuilder('rt')
            ->where('rt.username = :user_identifier')
            ->setParameter('user_identifier', $user->getUserIdentifier(), ParameterType::STRING)
            ->orderBy('rt.valid', 'DESC')
            ->setFirstResult($keep)
            ->getQuery()
            ->getResult();

        if ([] === $stale) {
            return 0;
        }

        /** @var int $deleted */
        $deleted = $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (RefreshToken $token): int|string|null => $token->getId(), $stale))
            ->getQuery()
            ->execute();

        return $deleted;
    }

    #[\Override]
    public function deleteToken(RefreshTokenInterface $refreshToken): int
    {
        /** @var int $deleted */
        $deleted = $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.id = :id')
            ->setParameter('id', $refreshToken->getId())
            ->getQuery()
            ->execute();

        return $deleted;
    }
}
