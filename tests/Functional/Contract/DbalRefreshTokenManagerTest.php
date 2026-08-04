<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
final class DbalRefreshTokenManagerTest extends TestCase
{
    use RefreshTokenManagerContract;
    use RevokeRefreshTokenManagerContract;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        (new TableSchemaManager($this->connection, 'refresh_tokens', []))->createTable(true);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new RefreshTokenManager($this->connection, $batchSize, 'refresh_tokens', RefreshToken::class);
    }

    protected function revokingManager(): RevokeRefreshTokenManagerInterface&RefreshTokenManagerInterface
    {
        $manager = $this->manager();

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);

        return $manager;
    }
}
