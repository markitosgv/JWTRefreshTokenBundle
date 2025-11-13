<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Doctrine\DBAL;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class RefreshTokenManagerTest extends TestCase
{
    private Connection $connection;
    private RefreshTokenManager $manager;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->manager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            'refresh_tokens',
            RefreshToken::class
        );
        $this->manager->createTable(true);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSaveAndRetrieveToken(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('test-token-123');
        $token->setUsername('testuser');
        $token->setValid(new DateTime('+1 hour'));

        $this->manager->save($token);
        $retrieved = $this->manager->get('test-token-123');

        $this->assertNotNull($retrieved);
        $this->assertSame('test-token-123', $retrieved->getRefreshToken());
        $this->assertSame('testuser', $retrieved->getUsername());
    }

    public function testDeleteToken(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('delete-me');
        $token->setUsername('user');
        $token->setValid(new DateTime('+1 hour'));

        $this->manager->save($token);
        $this->manager->delete($token);

        $this->assertNull($this->manager->get('delete-me'));
    }

    public function testRevokeExpiredTokens(): void
    {
        $valid = new RefreshToken();
        $valid->setRefreshToken('valid-token');
        $valid->setUsername('user1');
        $valid->setValid(new DateTime('+1 hour'));
        $this->manager->save($valid);

        $expired = new RefreshToken();
        $expired->setRefreshToken('expired-token');
        $expired->setUsername('user2');
        $expired->setValid(new DateTime('-1 hour'));
        $this->manager->save($expired);

        $revoked = $this->manager->revokeAllInvalid();

        $this->assertCount(1, $revoked);
        $this->assertSame('expired-token', $revoked[0]->getRefreshToken());
        $this->assertNotNull($this->manager->get('valid-token'));
        $this->assertNull($this->manager->get('expired-token'));
    }
}
