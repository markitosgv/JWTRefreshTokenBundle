<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Command;

use DateTimeInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine\CachingIteratorDouble;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearInvalidRefreshTokensCommandTest extends TestCase
{
    public function test_clears_tokens_without_timestamp(): void
    {
        /** @var MockObject|RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject|RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalid')
            ->with($this->isInstanceOf(DateTimeInterface::class))
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    public function test_clears_tokens_with_timestamp(): void
    {
        /** @var MockObject|RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject|RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalid')
            ->with($this->isInstanceOf(DateTimeInterface::class))
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['datetime' => '2021-01-01']);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    /**
     * When using the ODM, the repository returns a CachingIterator instead of an array. The command
     * used to crash with "array_map(): Argument #2 ($array) must be of type array,
     * Doctrine\ODM\MongoDB\Iterator\CachingIterator given".
     *
     * This wires the real manager to a repository returning an iterator to cover the whole path.
     */
    public function test_clears_tokens_when_the_repository_returns_an_iterator(): void
    {
        /** @var MockObject|RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject|RefreshTokenRepository $repository */
        $repository = $this->createMock(RefreshTokenRepository::class);
        $repository->expects($this->once())
            ->method('findInvalid')
            ->willReturn(new CachingIteratorDouble([$refreshToken]));

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')
            ->willReturn(RefreshToken::class);

        /** @var MockObject|ObjectManager $objectManager */
        $objectManager = $this->createMock(ObjectManager::class);
        $objectManager->method('getRepository')
            ->willReturn($repository);
        $objectManager->method('getClassMetadata')
            ->willReturn($classMetadata);
        $objectManager->expects($this->once())
            ->method('remove')
            ->with($refreshToken);

        $command = new ClearInvalidRefreshTokensCommand(
            new RefreshTokenManager($objectManager, RefreshToken::class)
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }
}
