<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Command;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Model\ExpiredRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
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
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

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
        $commandTester->execute(['datetime' => '2021-01-01'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    /**
     * Clearing a backlog revokes thousands of tokens, and listing them all buries the count.
     */
    public function test_does_not_list_the_tokens_unless_asked_to(): void
    {
        /** @var Stub&RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getRefreshToken')->willReturn('refresh-token');

        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllInvalidBatch')
            ->willReturn([$refreshToken]);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The count is always reported');
        $this->assertStringNotContainsString('* refresh-token', $output, 'The tokens themselves need -v');
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

    public function test_rejects_a_batch_size_that_is_not_a_number(): void
    {
        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->never())
            ->method('revokeAllInvalidBatch');

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--batch-size' => 'every-single-one']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('must each be a single value', $commandTester->getDisplay());
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
        $commandTester->execute(['--batch-size' => $batchSize], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 1 invalid token(s)', $output, 'The output should include a summary of the number of invalidated tokens');
        $this->assertStringContainsString('* refresh-token', $output, 'The output should list all invalidated tokens');
    }

    /**
     * The point of the flag: one statement, so what it costs does not grow with the backlog. The
     * batching path must not be taken as well.
     */
    public function test_clears_the_tokens_directly_when_asked_to(): void
    {
        /** @var ExpiredRefreshTokenManagerInterface&MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, ExpiredRefreshTokenManagerInterface::class]);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllExpired')
            ->with($this->isInstanceOf(DateTimeInterface::class))
            ->willReturn(7);
        $refreshTokenManager->expects($this->never())
            ->method('revokeAllInvalidBatch');

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--direct' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Revoked 7 invalid token(s) directly', $commandTester->getDisplay());
    }

    public function test_passes_the_given_date_on_when_clearing_directly(): void
    {
        /** @var ExpiredRefreshTokenManagerInterface&MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, ExpiredRefreshTokenManagerInterface::class]);
        $refreshTokenManager->expects($this->once())
            ->method('revokeAllExpired')
            ->with($this->callback(static fn (DateTimeInterface $datetime): bool => '2021-01-01' === $datetime->format('Y-m-d')))
            ->willReturn(1);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['datetime' => '2021-01-01', '--direct' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Nothing was read, so there is nothing to list. Saying so beats printing the count and leaving
     * someone to wonder where their -v output went.
     */
    public function test_says_the_tokens_cannot_be_listed_when_cleared_directly(): void
    {
        /** @var ExpiredRefreshTokenManagerInterface&RefreshTokenManagerInterface&Stub $refreshTokenManager */
        $refreshTokenManager = $this->createStubForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, ExpiredRefreshTokenManagerInterface::class]);
        $refreshTokenManager->method('revokeAllExpired')->willReturn(3);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--direct' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Revoked 3 invalid token(s) directly', $output);
        $this->assertStringContainsString('cannot be listed', $output);
    }

    public function test_reports_when_there_was_nothing_to_clear_directly(): void
    {
        /** @var ExpiredRefreshTokenManagerInterface&RefreshTokenManagerInterface&Stub $refreshTokenManager */
        $refreshTokenManager = $this->createStubForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, ExpiredRefreshTokenManagerInterface::class]);
        $refreshTokenManager->method('revokeAllExpired')->willReturn(0);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--direct' => true]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('There were no invalid tokens to revoke.', $commandTester->getDisplay());
    }

    /**
     * A manager of your own, or the cache one, implements only the base interface. Deleting nothing
     * while reporting success is the one outcome that must not happen.
     */
    public function test_refuses_to_clear_directly_when_the_manager_cannot(): void
    {
        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->never())
            ->method('revokeAllInvalidBatch');

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--direct' => true]);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        // The console wraps the line, so only a fragment that survives the wrapping is asserted on
        $this->assertStringContainsString('cannot clear tokens', $commandTester->getDisplay());
    }

    /**
     * The tokens go in one statement, so the option has nothing to do. Ignoring it quietly would
     * leave someone believing their batches were being honoured.
     */
    public function test_warns_that_the_batch_size_is_ignored_when_clearing_directly(): void
    {
        /** @var ExpiredRefreshTokenManagerInterface&RefreshTokenManagerInterface&Stub $refreshTokenManager */
        $refreshTokenManager = $this->createStubForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, ExpiredRefreshTokenManagerInterface::class]);
        $refreshTokenManager->method('revokeAllExpired')->willReturn(1);

        $command = new ClearInvalidRefreshTokensCommand($refreshTokenManager, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--direct' => true, '--batch-size' => 10]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('batch size is ignored with --direct', $commandTester->getDisplay());
    }
}
