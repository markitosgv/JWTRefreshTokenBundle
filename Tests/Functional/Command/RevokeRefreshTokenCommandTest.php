<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Command;

use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class RevokeRefreshTokenCommandTest extends TestCase
{
    public function test_does_not_revoke_a_nonexisting_token(): void
    {
        $token = 'refresh-token';

        /** @var MockObject|RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('get')
            ->with($token)
            ->willReturn(null);

        $command = new RevokeRefreshTokenCommand($refreshTokenManager);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['refresh_token' => $token]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('does not exist', $commandTester->getDisplay());
    }

    public function test_revokes_a_token(): void
    {
        $token = 'refresh-token';

        /** @var MockObject|RefreshTokenInterface $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        /** @var MockObject|RefreshTokenManagerInterface $refreshTokenManager */
        $refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $refreshTokenManager->expects($this->once())
            ->method('get')
            ->with($token)
            ->willReturn($refreshToken);

        $command = new RevokeRefreshTokenCommand($refreshTokenManager);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['refresh_token' => $token]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Revoked refresh token', $commandTester->getDisplay());
    }
}
