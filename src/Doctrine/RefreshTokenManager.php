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

use DateTime;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use LogicException;
use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class RefreshTokenManager implements ListRefreshTokenManagerInterface, RefreshTokenManagerInterface, RevokeRefreshTokenManagerInterface
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

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        return $this->repository->findOneBy(['refreshToken' => $refreshToken]);
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        return $this->repository->findOneBy(['username' => $username], ['valid' => 'DESC']);
    }

    #[\Override]
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
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     */
    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        // Asking the storage what it deleted is the only answer that holds when two callers race
        // for the same token: reading it back first tells both of them they won
        if ($this->repository instanceof DeleteRefreshTokenRepositoryInterface) {
            $deleted = $this->repository->deleteToken($refreshToken);

            $this->objectManager->detach($refreshToken);

            return $deleted;
        }

        if (null === $this->repository->findOneBy(['id' => $refreshToken->getId()])) {
            return 0;
        }

        $this->objectManager->remove($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }

        return 1;
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * When the tokens are flushed, every batch is deleted from the storage before the next one is
     * read, so the remaining tokens shift down and the offset must stay where it is. Advancing it
     * would skip one batch for every batch deleted. The offset is only paged forward when the
     * caller takes care of the flush, since nothing has been deleted yet in that case.
     *
     * @param ?DateTimeInterface $datetime  The date and time to consider for invalidation
     * @param ?positive-int      $batchSize Number of tokens to process per batch, defaults to the {@see $defaultBatchSize} property when not provided
     * @param int<0, max>        $offset    The offset to start processing from, defaults to 0
     * @param bool               $andFlush  Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     */
    #[\Override]
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        $batchSize ??= $this->defaultBatchSize;
        $revokedTokens = [];

        do {
            $invalidTokens = self::toArray($this->repository->findInvalidBatch($datetime, $batchSize, $offset));

            foreach ($invalidTokens as $invalidToken) {
                $this->objectManager->remove($invalidToken);
                $revokedTokens[] = $invalidToken;
            }

            if ($andFlush && [] !== $invalidTokens) {
                $this->objectManager->flush();
                $this->objectManager->clear();
            } else {
                $offset += $batchSize;
            }
        } while ([] !== $invalidTokens);

        return $revokedTokens;
    }

    /**
     * Revokes all invalid (expired) refresh tokens.
     *
     * @param ?DateTimeInterface $datetime The date and time to consider for invalidation
     * @param bool               $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     */
    #[\Override]
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $invalidTokens = self::toArray($this->repository->findInvalid($datetime));

        foreach ($invalidTokens as $invalidToken) {
            $this->objectManager->remove($invalidToken);
        }

        if ($andFlush && [] !== $invalidTokens) {
            $this->objectManager->flush();
            $this->objectManager->clear();
        }

        return $invalidTokens;
    }

    /**
     * Normalizes the repository result to a list.
     *
     * The repositories are typed to return an `iterable`; the ORM gives back an array, but the ODM
     * gives back a {@see \Doctrine\ODM\MongoDB\Iterator\CachingIterator}, which is not an array.
     *
     * @param iterable<RefreshTokenInterface> $tokens
     *
     * @return list<RefreshTokenInterface>
     */
    private static function toArray(iterable $tokens): array
    {
        return is_array($tokens) ? array_values($tokens) : iterator_to_array($tokens, false);
    }

    #[\Override]
    public function revokeAllForUser(UserInterface $user): int
    {
        if (!$this->repository instanceof DeleteRefreshTokenRepositoryInterface) {
            throw new LogicException(sprintf('Repository mapped for "%s" should implement %s.', $this->class, DeleteRefreshTokenRepositoryInterface::class));
        }

        return $this->repository->deleteByUser($user);
    }

    public function revokeAllExpired(DateTimeInterface $datetime = new DateTime()): int
    {
        if (!method_exists($this->repository, 'deleteAllExpired')) {
            throw new LogicException(sprintf('Repository mapped for "%s" should implement method "deleteAllExpired".', $this->class));
        }

        return $this->repository->deleteAllExpired($datetime);
    }

    #[\Override]
    public function findAllForUser(UserInterface $user): array
    {
        // findBy() takes an order and is on ObjectRepository already, so no repository of a
        // particular kind is needed for this
        return $this->repository->findBy(
            ['username' => $user->getUserIdentifier()],
            ['valid' => 'DESC']
        );
    }

    #[\Override]
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int
    {
        if (!$this->repository instanceof DeleteRefreshTokenRepositoryInterface) {
            throw new LogicException(sprintf('Repository mapped for "%s" should implement %s.', $this->class, DeleteRefreshTokenRepositoryInterface::class));
        }

        return $this->repository->deleteAllButNewestForUser($user, $keep);
    }

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     */
    #[\Override]
    public function getClass(): string
    {
        return $this->class;
    }
}
