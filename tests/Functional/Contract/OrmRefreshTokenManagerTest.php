<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_sqlite')]
final class OrmRefreshTokenManagerTest extends ORMTestCase
{
    use FamilyAwareRefreshTokenManagerContract;
    use ListRefreshTokenManagerContract;
    use RefreshTokenManagerContract;
    use RevokeRefreshTokenManagerContract;

    protected function setUp(): void
    {
        parent::setUp();

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new RefreshTokenManager($this->entityManager, RefreshToken::class, $batchSize);
    }

    protected function forgetLoadedObjects(): void
    {
        $this->entityManager->clear();
    }

    protected function revokingManager(): RevokeRefreshTokenManagerInterface&RefreshTokenManagerInterface
    {
        $manager = $this->manager();

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);

        return $manager;
    }

    protected function listingManager(): ListRefreshTokenManagerInterface&RefreshTokenManagerInterface
    {
        $manager = $this->manager();

        \assert($manager instanceof ListRefreshTokenManagerInterface);

        return $manager;
    }
}
