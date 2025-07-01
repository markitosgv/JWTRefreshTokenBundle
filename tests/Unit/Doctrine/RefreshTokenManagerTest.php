<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

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
        $this->objectManager
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
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

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
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->objectManager
            ->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $this->objectManager
            ->expects($this->once())
            ->method('flush');

        $this->refreshTokenManager->delete($refreshToken, true);
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

    public function testProvidesTheModelClass(): void
    {
        $this->assertSame(static::REFRESH_TOKEN_ENTITY_CLASS, $this->refreshTokenManager->getClass());
    }
}
