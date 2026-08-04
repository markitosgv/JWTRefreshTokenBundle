<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

use DateTimeInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DeleteRefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\MockObject\MockObject;
use LogicException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * The collaborators are built once in setUp(), so a test only sets expectations on the ones it
 * is about. The others stay mock objects without any.
 */
#[AllowMockObjectsWithoutExpectations]
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
            ->willReturn(self::REFRESH_TOKEN_ENTITY_CLASS);

        $this->objectManager = $this->createMock(ObjectManager::class);
        // getRepository() may be called any number of times, so the class it is asked for is
        // checked on the call itself rather than with an expected count
        $this->objectManager
            ->method('getRepository')
            ->willReturnCallback(function (string $class): RefreshTokenRepository {
                $this->assertSame(self::REFRESH_TOKEN_ENTITY_CLASS, $class);

                return $this->repository;
            });

        $this->objectManager
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with(self::REFRESH_TOKEN_ENTITY_CLASS)
            ->willReturn($classMetadata);

        $this->refreshTokenManager = new RefreshTokenManager(
            $this->objectManager,
            self::REFRESH_TOKEN_ENTITY_CLASS,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
        );
    }

    public function testRetrievesATokenFromStorage(): void
    {
        $token = 'token';
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

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
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

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
            ->expects($this->once())
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

    public function testDoesNotDeleteARefreshTokenThatIsNotInStorage(): void
    {
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken
            ->method('getId')
            ->willReturn(123);

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn(null);

        $this->objectManager
            ->expects($this->never())
            ->method('remove');

        $this->objectManager
            ->expects($this->never())
            ->method('flush');

        $this->assertSame(0, $this->refreshTokenManager->delete($refreshToken, true));
    }

    public function testRevokesAllInvalidTokensAndFlushesTheObjectManager(): void
    {
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

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
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $remainingTokens = [$refreshToken];
        $this->repository
            ->expects($this->exactly(2))
            ->method('findInvalidBatch')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use (&$remainingTokens) {
                $this->assertNull($arg1);
                $this->assertSame(1000, $arg2);
                $this->assertSame(0, $arg3);

                return array_splice($remainingTokens, 0, 1000);
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
        $allTokens = [
            $this->createMock(RefreshTokenInterface::class),
            $this->createMock(RefreshTokenInterface::class),
            $this->createMock(RefreshTokenInterface::class),
        ];
        $remainingTokens = $allTokens;

        $this->repository
            ->expects($this->exactly(3))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use (&$remainingTokens): array {
                return array_splice($remainingTokens, 0, 2);
            });

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalidBatch(null, 2, 0, true);

        $this->assertSame($allTokens, $revokedTokens, 'All the revoked tokens should be returned, not only the ones of the last batch');
    }

    public function testDoesNotSkipTokensWhenTheBatchesAreFlushed(): void
    {
        $tokens = [
            $this->createMock(RefreshTokenInterface::class),
            $this->createMock(RefreshTokenInterface::class),
            $this->createMock(RefreshTokenInterface::class),
        ];

        // The storage is emptied batch by batch, so the offset must always point at the first
        // token still left. Anything else would skip the tokens that shifted down.
        $offsets = [];
        $this->repository
            ->expects($this->exactly(3))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use (&$tokens, &$offsets): array {
                $offsets[] = $offset;

                return array_splice($tokens, 0, 2);
            });

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalidBatch(null, 2, 0, true);

        $this->assertSame([0, 0, 0], $offsets, 'The offset should not move forward while the batches are being deleted');
        $this->assertCount(3, $revokedTokens, 'Every invalid token should be revoked');
    }

    public function testPagesForwardWhenTheCallerTakesCareOfTheFlush(): void
    {
        $token = $this->createStub(RefreshTokenInterface::class);

        // Nothing is deleted until the caller flushes, so the offset has to page forward.
        $offsets = [];
        $this->repository
            ->expects($this->exactly(3))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use ($token, &$offsets): array {
                $offsets[] = $offset;

                return $offset < 4 ? [$token, $token] : [];
            });

        $this->objectManager
            ->expects($this->never())
            ->method('flush');

        $this->refreshTokenManager->revokeAllInvalidBatch(null, 2, 0, false);

        $this->assertSame([0, 2, 4], $offsets, 'The offset should page forward when nothing is deleted');
    }

    public function testRevokesAllInvalidTokensInBatchesWhenTheRepositoryReturnsAnIterator(): void
    {
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

        $remainingTokens = [$refreshToken];
        $this->repository
            ->expects($this->exactly(2))
            ->method('findInvalidBatch')
            ->willReturnCallback(static function (?DateTimeInterface $datetime, ?int $batchSize, int $offset) use (&$remainingTokens): CachingIteratorDouble {
                return new CachingIteratorDouble(array_splice($remainingTokens, 0, 1000));
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
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

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

    public function testRefusesAnObjectManagerWhoseRepositoryDoesNotImplementTheInterface(): void
    {
        $objectManager = $this->createStub(ObjectManager::class);
        $objectManager
            ->method('getRepository')
            ->willReturn($this->createMock(ObjectRepository::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Repository mapped for "%s" should implement %s.', RefreshToken::class, RefreshTokenRepositoryInterface::class));

        new RefreshTokenManager($objectManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);
    }

    public function testRevokesEveryTokenIssuedToAUser(): void
    {
        $user = UserCreator::create('user@localhost');

        $this->repository
            ->expects($this->once())
            ->method('deleteByUser')
            ->with($user)
            ->willReturn(3);

        $this->assertSame(3, $this->refreshTokenManager->revokeAllForUser($user), 'The number of revoked tokens should reach the caller');
    }

    public function testRefusesToRevokeForAUserWhenTheRepositoryCannotDelete(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn(self::REFRESH_TOKEN_ENTITY_CLASS);

        // A repository predating DeleteRefreshTokenRepositoryInterface still satisfies the rest
        $objectManager = $this->createStub(ObjectManager::class);
        $objectManager->method('getRepository')->willReturn($this->createStub(RefreshTokenRepositoryInterface::class));
        $objectManager->method('getClassMetadata')->willReturn($metadata);

        $manager = new RefreshTokenManager($objectManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Repository mapped for "%s" should implement %s.', RefreshToken::class, DeleteRefreshTokenRepositoryInterface::class));

        $manager->revokeAllForUser(UserCreator::create());
    }

    public function testProvidesTheModelClass(): void
    {
        $this->assertSame(self::REFRESH_TOKEN_ENTITY_CLASS, $this->refreshTokenManager->getClass());
    }
}
