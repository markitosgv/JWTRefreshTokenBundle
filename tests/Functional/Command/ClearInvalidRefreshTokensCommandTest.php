<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Command;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearInvalidRefreshTokensCommandTest extends TestCase
{
    public function test_clears_tokens_without_timestamp(): void
    {
        /** @var MockObject&RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalidBatch')
            ->with($this->isInstanceOf(DateTimeInterface::class), RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE)
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    public function test_clears_tokens_with_timestamp(): void
    {
        /** @var MockObject&RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalidBatch')
            ->with($this->isInstanceOf(DateTimeInterface::class), RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE)
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['datetime' => '2021-01-01']);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    public function test_reports_when_there_was_nothing_to_revoke(): void
    {
        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalidBatch')
            ->willReturn([]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('There were no invalid tokens to revoke.', $commandTester->getDisplay());
    }

    public function test_rejects_a_batch_size_that_is_not_positive(): void
    {
        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->never())
            ->method('revokeAllInvalidBatch');

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--batch-size' => 0]);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode(), 'A batch size of zero would revoke nothing while reporting success');
        $this->assertStringContainsString('The batch size must be a positive integer.', $commandTester->getDisplay());
    }

    public function test_clears_tokens_with_custom_batch_size(): void
    {
        $batchSize = 5;

        /** @var MockObject&RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalidBatch')
            ->with($this->isInstanceOf(DateTimeInterface::class), $batchSize)
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--batch-size' => $batchSize]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }
}
