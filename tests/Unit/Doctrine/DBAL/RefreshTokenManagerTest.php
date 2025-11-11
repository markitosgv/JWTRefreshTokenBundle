<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine\DBAL;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefreshTokenManagerTest extends TestCase
{
    public const REFRESH_TOKEN_CLASS = RefreshToken::class;
    public const TABLE_NAME = 'refresh_tokens';

    private MockObject&Connection $connection;
    private RefreshTokenManager $refreshTokenManager;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->refreshTokenManager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            self::TABLE_NAME,
            self::REFRESH_TOKEN_CLASS,
        );
    }

    public function testRetrievesATokenFromStorage(): void
    {
        $tokenString = 'test-token';
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(self::TABLE_NAME)
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('refresh_token = :refreshToken')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('refreshToken', $tokenString)
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 1,
                'refresh_token' => $tokenString,
                'username' => 'user',
                'valid' => new DateTimeImmutable('+1 hour'),
            ]);

        $result = $this->refreshTokenManager->get($tokenString);

        $this->assertInstanceOf(RefreshTokenInterface::class, $result);
        $this->assertSame($tokenString, $result->getRefreshToken());
    }

    public function testReturnsNullWhenTheTokenDoesNotExistInStorage(): void
    {
        $tokenString = 'non-existent-token';
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->refreshTokenManager->get($tokenString);

        $this->assertNull($result);
    }

    public function testRetrievesTheLastTokenForAUserFromStorage(): void
    {
        $username = 'test-user';
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('valid', 'DESC')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 1,
                'refresh_token' => 'token',
                'username' => $username,
                'valid' => new DateTimeImmutable('+1 hour'),
            ]);

        $result = $this->refreshTokenManager->getLastFromUsername($username);

        $this->assertInstanceOf(RefreshTokenInterface::class, $result);
        $this->assertSame($username, $result->getUsername());
    }

    public function testReturnsNullWhenAUserDoesNotHaveATokenInStorage(): void
    {
        $username = 'non-existent-user';
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->refreshTokenManager->getLastFromUsername($username);

        $this->assertNull($result);
    }

    public function testDeletesTheRefreshToken(): void
    {
        $tokenString = 'token-to-delete';
        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken($tokenString);
        $refreshToken->setUsername('user');
        $refreshToken->setValid(new DateTimeImmutable('+1 hour'));

        $this->connection
            ->expects($this->once())
            ->method('delete')
            ->with(self::TABLE_NAME, ['refresh_token' => $tokenString])
            ->willReturn(1);

        $result = $this->refreshTokenManager->delete($refreshToken, true);

        $this->assertSame(1, $result);
    }

    public function testRevokesAllInvalidTokensWithPostgreSQL(): void
    {
        $platform = $this->createMock(PostgreSQLPlatform::class);
        $result = $this->getMockBuilder(Result::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $result
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 1,
                    'refresh_token' => 'expired-token',
                    'username' => 'user',
                    'valid' => new DateTimeImmutable('-1 hour'),
                ],
            ]);

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->stringContains('DELETE FROM'),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($result);

        $this->connection
            ->expects($this->once())
            ->method('commit');

        $tokens = $this->refreshTokenManager->revokeAllInvalid(null, true);

        $this->assertCount(1, $tokens);
        $this->assertInstanceOf(RefreshTokenInterface::class, $tokens[0]);
    }

    public function testRevokesAllInvalidTokensWithMySQL(): void
    {
        $platform = $this->createMock(MySQLPlatform::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $deleteQueryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->connection
            ->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($queryBuilder, $deleteQueryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 1,
                    'refresh_token' => 'expired-token',
                    'username' => 'user',
                    'valid' => new DateTimeImmutable('-1 hour'),
                ],
            ]);

        $deleteQueryBuilder->method('delete')->willReturnSelf();
        $deleteQueryBuilder->method('where')->willReturnSelf();
        $deleteQueryBuilder->method('setParameter')->willReturnSelf();

        $deleteQueryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('commit');

        $tokens = $this->refreshTokenManager->revokeAllInvalid(null, true);

        $this->assertCount(1, $tokens);
        $this->assertInstanceOf(RefreshTokenInterface::class, $tokens[0]);
    }

    public function testRevokesAllInvalidTokensReturnsEmptyArrayWhenNoTokens(): void
    {
        $platform = $this->createMock(MySQLPlatform::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connection
            ->expects($this->once())
            ->method('commit');

        $tokens = $this->refreshTokenManager->revokeAllInvalid(null, true);

        $this->assertCount(0, $tokens);
    }

    public function testRevokesAllInvalidTokensInBatches(): void
    {
        $queryBuilder1 = $this->createMock(QueryBuilder::class);
        $queryBuilder2 = $this->createMock(QueryBuilder::class);

        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->connection
            ->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($queryBuilder1, $queryBuilder2);

        // First batch - returns data
        $queryBuilder1->method('select')->willReturnSelf();
        $queryBuilder1->method('from')->willReturnSelf();
        $queryBuilder1->method('where')->willReturnSelf();
        $queryBuilder1->method('setParameter')->willReturnSelf();
        $queryBuilder1->method('setMaxResults')->willReturnSelf();
        $queryBuilder1->method('setFirstResult')->willReturnSelf();

        $queryBuilder1
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 1,
                    'refresh_token' => 'expired-token',
                    'username' => 'user',
                    'valid' => new DateTimeImmutable('-1 hour'),
                ],
            ]);

        // Second batch - returns empty
        $queryBuilder2->method('select')->willReturnSelf();
        $queryBuilder2->method('from')->willReturnSelf();
        $queryBuilder2->method('where')->willReturnSelf();
        $queryBuilder2->method('setParameter')->willReturnSelf();
        $queryBuilder2->method('setMaxResults')->willReturnSelf();
        $queryBuilder2->method('setFirstResult')->willReturnSelf();

        $queryBuilder2
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('commit');

        $tokens = $this->refreshTokenManager->revokeAllInvalidBatch(null, 100, 0, true);

        $this->assertCount(1, $tokens);
        $this->assertInstanceOf(RefreshTokenInterface::class, $tokens[0]);
    }

    public function testProvidesTheModelClass(): void
    {
        $this->assertSame(self::REFRESH_TOKEN_CLASS, $this->refreshTokenManager->getClass());
    }
}
