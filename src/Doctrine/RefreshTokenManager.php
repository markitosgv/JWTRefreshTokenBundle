<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use LogicException;
use DateTimeInterface;

final readonly class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * @var class-string<RefreshTokenInterface>
     */
    private string $class;

    /**
     * @var RefreshTokenRepositoryInterface<RefreshTokenInterface>
     */
    private RefreshTokenRepositoryInterface $repository;

    /**
     * @param class-string<RefreshTokenInterface> $class
     * @param positive-int                        $defaultBatchSize
     *
     * @throws LogicException if the object repository does not implement {@see RefreshTokenRepositoryInterface}
     */
    public function __construct(
        private ObjectManager $objectManager,
        string $class,
        private int $defaultBatchSize,
    ) {
        $repository = $this->objectManager->getRepository($class);

        if (!$repository instanceof RefreshTokenRepositoryInterface) {
            throw new LogicException(sprintf('Repository mapped for "%s" should implement %s.', $class, RefreshTokenRepositoryInterface::class));
        }

        $this->repository = $repository;

        $this->class = $this->objectManager->getClassMetadata($class)->getName();
    }

    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        return $this->repository->findOneBy(['refreshToken' => $refreshToken]);
    }

    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        return $this->repository->findOneBy(['username' => $username], ['valid' => 'DESC']);
    }

    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $this->objectManager->persist($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * Deletes the given refresh token and returns the number of rows affected.
     *
     * @param RefreshTokenInterface $refreshToken
     * @param bool $andFlush
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     */
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        // Use DQL if this is an ORM EntityManager
        if (is_a($this->objectManager, '\Doctrine\ORM\EntityManagerInterface')) {
            $q = $this->objectManager->createQuery('DELETE FROM ' . $this->class . ' rt WHERE rt.id = :id');
            $q->setParameter('id', $refreshToken->getId());
            $numDeleted = $q->execute();
            if ($andFlush) {
                $this->objectManager->flush();
            }
            return $numDeleted;
        }
        // Fallback for ODM or other managers: remove and flush, but cannot return affected rows
        $this->objectManager->remove($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }
        // We assume 1 row affected if no exception was thrown
        return 1;
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * @param ?DateTimeInterface $datetime  The date and time to consider for invalidation
     * @param ?positive-int      $batchSize Number of tokens to process per batch, defaults to the {@see $defaultBatchSize} property when not provided
     * @param ?int<0, max>       $offset    The offset to start processing from, defaults to 0
     * @param bool               $andFlush  Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        $batchSize ??= $this->defaultBatchSize;
        $count = 0;

        do {
            $invalidTokens = $this->repository->findInvalidBatch($datetime, $batchSize, $offset);

            foreach ($invalidTokens as $invalidToken) {
                $this->objectManager->remove($invalidToken);
                ++$count;
            }

            if ($andFlush && !empty($invalidToken)) {
                $this->objectManager->flush();
                $this->objectManager->clear();
            }

            $offset += $batchSize;
        } while (!empty($invalidTokens));

        return $invalidTokens ?? [];
    }

    /**
     * Revokes all invalid (expired) refresh tokens.
     *
     * @param ?DateTimeInterface $datetime The date and time to consider for invalidation
     * @param bool               $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $invalidTokens = $this->repository->findInvalid($datetime);

        foreach ($invalidTokens as $invalidToken) {
            $this->objectManager->remove($invalidToken);
        }

        if ($andFlush && !empty($invalidToken)) {
            $this->objectManager->flush();
            $this->objectManager->clear();
        }

        return $invalidTokens ?? [];
    }

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
