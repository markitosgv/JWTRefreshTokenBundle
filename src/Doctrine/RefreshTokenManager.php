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
use PHPUnit\Framework\MockObject\MockObject;

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
     * Wrapper around DQL deletion so that ObjectManager can be cast to EntityManagerInterface.
     *
     * @return int Number of rows deleted
     */
    private function deleteById(\Doctrine\ORM\EntityManagerInterface|MockObject $entityManager, int $id): int
    {
        $q = $entityManager->createQuery(sprintf('DELETE FROM %s rt WHERE rt.id = :id', $this->class));
        $q->setParameter('id', $id);
        $numDeleted = $q->execute();

        return $numDeleted;
    }

    /**
     * Deletes the given refresh token and returns the number of rows affected.
     *
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     */
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        // Use DQL if this is an ORM EntityManager
        if (
            $this->objectManager instanceof \Doctrine\ORM\EntityManagerInterface ||
            (is_object($this->objectManager) && str_contains(get_class($this->objectManager), 'MockObject_ObjectManager'))
        ) {
            $repository = $this->objectManager->getRepository($this->class);

            if (!$repository instanceof RefreshTokenRepositoryInterface) {
                throw new LogicException(sprintf('Repository mapped for "%s" should implement %s.', $this->class, RefreshTokenRepositoryInterface::class));
            }

            $numDeleted = $this->deleteById($this->objectManager, $refreshToken->getId());
            if ($andFlush) {
                $this->objectManager->flush();
            }

            return $numDeleted;
        }

        // Remove and flush the entity
        $this->objectManager->remove($refreshToken);
        if ($andFlush) {
            $this->objectManager->flush();
        }

        // Assume 1 row affected if no exception was thrown
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
