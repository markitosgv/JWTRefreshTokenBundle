<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\BareRepositoryRefreshToken;

/**
 * An ORM repository offering only what `RefreshTokenRepositoryInterface` asks for, as one written
 * before `DeleteRefreshTokenRepositoryInterface` existed does.
 *
 * Unlike {@see BareRefreshTokenRepository}, it sits behind a real entity manager.
 *
 * @extends EntityRepository<BareRepositoryRefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<BareRepositoryRefreshToken>
 */
final class BareEntityRepository extends EntityRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @return iterable<BareRepositoryRefreshToken>
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->findInvalidBatch($datetime);
    }

    /**
     * @param positive-int|null $batchSize
     * @param int<0, max>       $offset
     *
     * @return iterable<BareRepositoryRefreshToken>
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        /** @var list<BareRepositoryRefreshToken> $tokens */
        $tokens = $this->createQueryBuilder('t')
            ->where('t.valid < :datetime')
            ->setParameter('datetime', $datetime ?? new DateTime())
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();

        return $tokens;
    }
}
