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
    const REFRESH_TOKEN_ENTITY_CLASS = RefreshToken::class;

    /**
     * @var RefreshTokenRepository|MockObject
     */
    private $repository;

    /**
     * @var ObjectManager|MockObject
     */
    private $objectManager;

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
            ->expects($this->once())
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
            static::REFRESH_TOKEN_ENTITY_CLASS
        );
    }

    public function testIsARefreshTokenManager()
    {
        $this->assertInstanceOf(RefreshTokenManagerInterface::class, $this->refreshTokenManager);
    }

    public function testRetrievesATokenFromStorage()
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

    public function testReturnsNullWhenTheTokenDoesNotExistInStorage()
    {
        $token = 'token';
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['refreshToken' => $token])
            ->willReturn(null);

        $this->assertNull($this->refreshTokenManager->get($token));
    }

    public function testRetrievesTheLastTokenForAUserFromStorage()
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

    public function testReturnsNullWhenAUserDoesNotHaveATokenInStorage()
    {
        $username = 'test';

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username], ['valid' => 'DESC'])
            ->willReturn(null);

        $this->assertNull($this->refreshTokenManager->getLastFromUsername($username));
    }

    public function testSavesTheRefreshTokenAndFlushesTheObjectManager()
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

    public function testDeletesTheRefreshTokenAndFlushesTheObjectManager()
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

    public function testRevokesAllInvalidTokensAndFlushesTheObjectManager()
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

    public function testProvidesTheModelClass()
    {
        $this->assertSame(static::REFRESH_TOKEN_ENTITY_CLASS, $this->refreshTokenManager->getClass());
    }
}
