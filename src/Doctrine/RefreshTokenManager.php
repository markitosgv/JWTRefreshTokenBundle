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
     *
     * @throws LogicException if the object repository does not implement {@see RefreshTokenRepositoryInterface}
     */
    public function __construct(private ObjectManager $objectManager, string $class)
    {
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

    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $this->objectManager->remove($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * @param bool $andFlush  Whether to flush the object manager after revoking
     * @param ?int $batchSize Number of tokens to process per batch
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($andFlush = true, ?int $batchSize = self::MAX_BATCH_SIZE): array
    {
        $repository = $this->objectManager->getRepository($this->class);
        $count = 0;
        $offset = 0;

        do {
            $invalidTokens = $repository->findBy(
                ['valid' => ['operator' => '<', 'value' => new \DateTime()]],
                null,
                $batchSize,
                $offset
            );

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

        return $$invalidTokens;
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
