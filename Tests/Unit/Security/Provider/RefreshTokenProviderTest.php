<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Provider;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RefreshTokenProviderTest extends TestCase
{
    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private $refreshTokenManager;

    private RefreshTokenProvider $refreshTokenProvider;

    protected function setUp(): void
    {
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);

        $this->refreshTokenProvider = new RefreshTokenProvider($this->refreshTokenManager);
    }
    public function testIsAUserProvider(): void
    {
        $this->assertInstanceOf(UserProviderInterface::class, $this->refreshTokenProvider);
    }

    public function testGetsTheUsernameFromATokenWhenTheTokenExistsInStorage(): void
    {
        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $token = 'my-refresh-token';
        $username = 'username';

        $this->createRefreshTokenManagerGetExpectation($token, $refreshToken);

        $refreshToken
            ->expects($this->once())
            ->method('getUsername')
            ->willReturn($username);

        $this->assertSame($username, $this->refreshTokenProvider->getUsernameForRefreshToken($token));
    }

    public function testReturnsNullWhenTheTokenDoesNotExistInStorage(): void
    {
        $token = 'my-refresh-token';
        $this->createRefreshTokenManagerGetExpectation($token, null);

        $this->assertNull($this->refreshTokenProvider->getUsernameForRefreshToken($token));
    }

    public function testLoadsAUserByUsername(): void
    {
        $this->assertInstanceOf(
            UserInterface::class,
            $this->refreshTokenProvider->loadUserByUsername('testname')
        );
    }

    public function testLoadsAUserByUsernameFromACustomUserProvider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);
        $this->refreshTokenProvider->setCustomUserProvider($userProvider);

        $this->assertInstanceOf(
            UserInterface::class,
            $this->refreshTokenProvider->loadUserByUsername('testname')
        );
    }

    public function testLoadsAUserByIdentifier(): void
    {
        $this->assertInstanceOf(
            UserInterface::class,
            $this->refreshTokenProvider->loadUserByIdentifier('testname')
        );
    }

    public function testLoadsAUserByIdentifierFromACustomUserProvider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);
        $this->refreshTokenProvider->setCustomUserProvider($userProvider);

        $this->assertInstanceOf(
            UserInterface::class,
            $this->refreshTokenProvider->loadUserByIdentifier('testname')
        );
    }

    public function testDoesNotSupportRefreshingAUserByDefault(): void
    {
        /** @var UserInterface|MockObject $user */
        $user = $this->createMock(UserInterface::class);

        $this->expectExceptionObject(new UnsupportedUserException());

        $this->refreshTokenProvider->refreshUser($user);
    }

    public function testRefreshesAUserWhenUsingACustomUserProvider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);

        if (method_exists($userProvider, 'loadUserByIdentifier')) {
            $user = $userProvider->loadUserByIdentifier('testname');
        } else {
            $user = $userProvider->loadUserByUsername('testname');
        }

        $this->refreshTokenProvider->setCustomUserProvider($userProvider);

        $this->assertInstanceOf(UserInterface::class, $this->refreshTokenProvider->refreshUser($user));
    }

    public function testSupportsAUserClass(): void
    {
        if (class_exists(InMemoryUser::class)) {
            $this->assertTrue($this->refreshTokenProvider->supportsClass(InMemoryUser::class));
        }

        $this->assertTrue($this->refreshTokenProvider->supportsClass(User::class));
    }

    public function testSupportsAUserClassWhenUsingACustomProvider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);
        $this->refreshTokenProvider->setCustomUserProvider($userProvider);

        if (class_exists(InMemoryUser::class)) {
            $this->assertTrue($this->refreshTokenProvider->supportsClass(InMemoryUser::class));
        }

        $this->assertTrue($this->refreshTokenProvider->supportsClass(User::class));
    }

    private function createRefreshTokenManagerGetExpectation(string $token, ?RefreshTokenInterface $refreshToken): void
    {
        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->with($token)
            ->willReturn($refreshToken);
    }
}
