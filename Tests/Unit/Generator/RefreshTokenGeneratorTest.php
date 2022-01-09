<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Generator;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;

class RefreshTokenGeneratorTest extends TestCase
{
    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private RefreshTokenManagerInterface $manager;

    private RefreshTokenGeneratorInterface $refreshTokenGenerator;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(RefreshTokenManagerInterface::class);

        $this->refreshTokenGenerator = new RefreshTokenGenerator($this->manager);
    }

    public function testGeneratesARefreshTokenWhenThereAreNoExistingTokens()
    {
        $this->manager
            ->expects($this->once())
            ->method('get')
            ->with($this->isType('string'))
            ->willReturn(null);

        $this->manager
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(RefreshToken::class);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->assertInstanceOf(
            RefreshTokenInterface::class,
            $this->refreshTokenGenerator->createForUserWithTtl($user, 600)
        );
    }

    public function testGeneratesARefreshTokenWhenThereIsAnExistingTokenMatchingTheGeneratedToken()
    {
        /** @var RefreshTokenInterface|MockObject $existingRefreshToken */
        $existingRefreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->manager
            ->expects($this->exactly(2))
            ->method('get')
            ->with($this->isType('string'))
            ->willReturn($existingRefreshToken, null);

        $this->manager
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(RefreshToken::class);

        $username = 'username';
        $password = 'password';
        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->assertInstanceOf(
            RefreshTokenInterface::class,
            $this->refreshTokenGenerator->createForUserWithTtl($user, 600)
        );
    }
}
