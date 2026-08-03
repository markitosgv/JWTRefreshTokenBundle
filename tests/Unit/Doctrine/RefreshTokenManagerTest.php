<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

use DateTimeInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefreshTokenManagerTest extends TestCase
{
    public const REFRESH_TOKEN_ENTITY_CLASS = RefreshToken::class;

    private MockObject&RefreshTokenRepository $repository;

    private MockObject&ObjectManager $objectManager;

    private RefreshTokenManager $refreshTokenManager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefreshTokenRepository::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata
            ->expects($this->once())
            ->method('getName')
            ->willReturn(static::REFRESH_TOKEN_ENTITY_CLASS);

        $this->objectManager = $this->createMock(ObjectManager::class);
        // Allow getRepository to be called any number of times with the expected argument
        $this->objectManager
            ->expects($this->any())
            ->method('getRepository')
            ->with(static::REFRESH_TOKEN_ENTITY_CLASS)
            ->willReturn($this->repository);

        $this->objectManager
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with(static::REFRESH_TOKEN_ENTITY_CLASS)
            ->willReturn($classMetadata);

        $this->refreshTokenManager = new RefreshTokenManager(
            $this->objectManager,
            static::REFRESH_TOKEN_ENTITY_CLASS,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
        );
    }

    public function testRetrievesATokenFromStorage(): void
    {
        $token = 'token';
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['refreshToken' => $token])
            ->willReturn($refreshToken);

        $this->assertSame($refreshToken, $this->refreshTokenManager->get($token));
    }

    public function testReturnsNullWhenTheTokenDoesNotExistInStorage(): void
    {
        $token = 'token';
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['refreshToken' => $token])
            ->willReturn(null);

        $this->assertNull($this->refreshTokenManager->get($token));
    }

    public function testRetrievesTheLastTokenForAUserFromStorage(): void
    {
        $username = 'test';
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username], ['valid' => 'DESC'])
            ->willReturn($refreshToken);

        $this->assertSame($refreshToken, $this->refreshTokenManager->getLastFromUsername($username));
    }

    public function testReturnsNullWhenAUserDoesNotHaveATokenInStorage(): void
    {
        $username = 'test';

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username], ['valid' => 'DESC'])
            ->willReturn(null);

        $this->assertNull($this->refreshTokenManager->getLastFromUsername($username));
    }

    public function testSavesTheRefreshTokenAndFlushesTheObjectManager(): void
    {
        /** @var RefreshTokenInterface&\PHPUnit\Framework\MockObject\MockObject $refreshToken */
        $refreshToken = $this->getMockBuilder(RefreshTokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager
            ->expects($this->once())
            ->method('persist')
            ->with($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('flush');

        $this->refreshTokenManager->save($refreshToken, true);
    }

    public function testDeletesTheRefreshTokenAndFlushesTheObjectManager(): void
    {
        /** @var RefreshTokenInterface&\PHPUnit\Framework\MockObject\MockObject $refreshToken */
        $refreshToken = $this->getMockBuilder(RefreshTokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Simula que el refreshToken tiene un id
        $refreshToken
            ->method('getId')
            ->willReturn(123);

        $this->repository
            ->method('findOneBy')
            ->with(['id' => $refreshToken->getId()])
            ->willReturn($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->refreshTokenManager->delete($refreshToken, true);
        $this->assertSame(1, $result);
    }

    public function testRevokesAllInvalidTokensAndFlushesTheObjectManager(): void
    {
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('findInvalid')
            ->with(null)
            ->willReturn([$refreshToken]);

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('flush');

        $this->refreshTokenManager->revokeAllInvalid(null, true);
    }

    public function testRevokesAllInvalidTokensInBatchesAndFlushesTheObjectManager(): void
    {
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $this->repository
            ->expects($this->exactly(2))
            ->method('findInvalidBatch')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use ($refreshToken) {
                if (null === $arg1 && 1000 === $arg2 && 0 === $arg3) {
                    return [$refreshToken];
                }
                if (null === $arg1 && 1000 === $arg2 && 1000 === $arg3) {
                    return [];
                }

                return null;
            });

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $this->objectManager
            ->method('flush');

        $this->refreshTokenManager->revokeAllInvalidBatch(null, 1000, 0, true);
    }

    public function testReturnsAllTheTokensRevokedInBatches(): void
    {
        $firstBatch = [$this->createMock(RefreshTokenInterface::class), $this->createMock(RefreshTokenInterface::class)];
        $secondBatch = [$this->createMock(RefreshTokenInterface::class)];

        $this->repository
            ->expects($this->exactly(3))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use ($firstBatch, $secondBatch): array {
                return match ($offset) {
                    0 => $firstBatch,
                    2 => $secondBatch,
                    default => [],
                };
            });

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalidBatch(null, 2, 0, true);

        $this->assertSame([...$firstBatch, ...$secondBatch], $revokedTokens, 'All the revoked tokens should be returned, not only the ones of the last batch');
    }

    public function testRevokesAllInvalidTokensInBatchesWhenTheRepositoryReturnsAnIterator(): void
    {
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->repository
            ->expects($this->exactly(2))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use ($refreshToken): CachingIteratorDouble {
                return new CachingIteratorDouble(0 === $offset ? [$refreshToken] : []);
            });

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalidBatch(null, 1000, 0, true);

        $this->assertSame([$refreshToken], $revokedTokens);
    }

    public function testRevokesAllInvalidTokensWhenTheRepositoryReturnsAnIterator(): void
    {
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->repository
            ->expects($this->once())
            ->method('findInvalid')
            ->with(null)
            ->willReturn(new CachingIteratorDouble([$refreshToken]));

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('flush');

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalid(null, true);

        $this->assertSame([$refreshToken], $revokedTokens);
    }

    public function testDoesNotFlushWhenThereAreNoInvalidTokensToRevoke(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findInvalid')
            ->with(null)
            ->willReturn(new CachingIteratorDouble());

        $this->objectManager
            ->expects($this->never())
            ->method('flush');

        $this->assertSame([], $this->refreshTokenManager->revokeAllInvalid(null, true));
    }

    public function testProvidesTheModelClass(): void
    {
        $this->assertSame(static::REFRESH_TOKEN_ENTITY_CLASS, $this->refreshTokenManager->getClass());
    }
}
