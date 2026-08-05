<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;

/**
 * A repository offering only what `RefreshTokenRepositoryInterface` asks for.
 *
 * The repositories that ship also implement `DeleteRefreshTokenRepositoryInterface`, so every other
 * test exercises the paths that use it. One written before that interface existed does not, and
 * this stands in for it.
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
final readonly class BareRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private RefreshTokenRepository $inner)
    {
    }

    /**
     * @return iterable<RefreshToken>
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        return $this->inner->findInvalid($datetime);
    }

    /**
     * @param positive-int|null $batchSize
     * @param int<0, max>       $offset
     *
     * @return iterable<RefreshToken>
     */
    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        return $this->inner->findInvalidBatch($datetime, $batchSize, $offset);
    }

    public function find($id): ?RefreshToken
    {
        return $this->inner->find($id);
    }

    /**
     * @return list<RefreshToken>
     */
    public function findAll(): array
    {
        return $this->inner->findAll();
    }

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return list<RefreshToken>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->inner->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?RefreshToken
    {
        return $this->inner->findOneBy($criteria, $orderBy);
    }

    /**
     * @return class-string<RefreshToken>
     */
    public function getClassName(): string
    {
        return RefreshToken::class;
    }
}
