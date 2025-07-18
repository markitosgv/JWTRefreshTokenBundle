<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
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

    private MockObject&EntityManagerInterface $entityManager;

    private RefreshTokenManager $refreshTokenManager;

    private RefreshTokenManager $refreshTokenManagerAlt;

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

        // alt setup for EntityManagerInterface (subclass of ObjectManager), required in testDeletesTheRefreshTokenAndFlushesTheObjectManager()
        $classMetadataAlt = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $classMetadataAlt
            ->expects($this->once())
            ->method('getName')
            ->willReturn(static::REFRESH_TOKEN_ENTITY_CLASS);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        // Allow getRepository to be called any number of times with the expected argument
        $this->entityManager
            ->expects($this->any())
            ->method('getRepository')
            ->with(static::REFRESH_TOKEN_ENTITY_CLASS)
            ->willReturn($this->repository);

        $this->entityManager
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with(static::REFRESH_TOKEN_ENTITY_CLASS)
            ->willReturn($classMetadataAlt);

        $this->refreshTokenManagerAlt = new RefreshTokenManager(
            $this->entityManager,
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

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $query
            ->method('execute')
            ->willReturn(1);

        // Simula que el refreshToken tiene un id
        $refreshToken
            ->method('getId')
            ->willReturn(123);

        $this->entityManager
            ->expects($this->once())
            ->method('createQuery')
            ->willReturn($query);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->refreshTokenManagerAlt->delete($refreshToken, true);
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

    public function testProvidesTheModelClass(): void
    {
        $this->assertSame(static::REFRESH_TOKEN_ENTITY_CLASS, $this->refreshTokenManager->getClass());
    }
}
