<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Command;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
        /** @var MockObject&RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('refresh-token');

        /** @var MockObject&RefreshTokenManagerInterface $refreshTokenManager */
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
}
