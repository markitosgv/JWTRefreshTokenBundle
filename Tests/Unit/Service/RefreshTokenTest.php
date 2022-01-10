<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Service;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class RefreshTokenTest extends TestCase
{
    /**
     * @var RefreshTokenAuthenticator|MockObject
     */
    private RefreshTokenAuthenticator $authenticator;

    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private RefreshTokenManagerInterface $refreshTokenManager;

    /**
     * @var AuthenticationFailureHandlerInterface|MockObject
     */
    private AuthenticationFailureHandlerInterface $failureHandler;

    private RefreshToken $refreshToken;

    protected function setUp(): void
    {
        $this->authenticator = $this->createMock(RefreshTokenAuthenticator::class);
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);

        $this->refreshToken = new RefreshToken(
            $this->authenticator,
            $this->createMock(RefreshTokenProvider::class),
            $this->createMock(AuthenticationSuccessHandlerInterface::class),
            $this->failureHandler,
            $this->refreshTokenManager,
            2592000,
            'testkey',
            false,
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    public function testItRefreshesToken()
    {
        $this->createAuthenticatorGetCredentialsExpectation(['token' => '1234']);
        $this->createAuthenticatorGetUserExpectation(new User('test', 'test'));
        $this->createAuthenticatorCreateAuthenticatedTokenExpectation(
            $this->createMock(PostAuthenticationGuardToken::class)
        );

        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken
            ->expects($this->once())
            ->method('isValid')
            ->willReturn(true);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn($refreshToken);

        $this->refreshToken->refresh($this->createMock(Request::class));
    }

    public function testItRefreshesTokenWithTtlUpdate()
    {
        $this->setTtlUpdateOnRefreshToken(true);

        $this->createAuthenticatorGetCredentialsExpectation(['token' => '1234']);
        $this->createAuthenticatorGetUserExpectation(new User('test', 'test'));
        $this->createAuthenticatorCreateAuthenticatedTokenExpectation(
            $this->createMock(PostAuthenticationGuardToken::class)
        );

        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $refreshToken
            ->expects($this->once())
            ->method('isValid')
            ->willReturn(true);

        $refreshToken
            ->expects($this->once())
            ->method('setValid');

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('save')
            ->with($refreshToken);

        $this->refreshToken->refresh($this->createMock(Request::class));
    }

    public function testItThrowsAnAuthenticationException()
    {
        $this->createAuthenticatorGetCredentialsExpectation(['token' => '1234']);
        $this->createAuthenticatorGetUserExpectation(new User('test', 'test'));
        $this->createAuthenticatorCreateAuthenticatedTokenExpectation(
            $this->createMock(PostAuthenticationGuardToken::class)
        );

        $this->failureHandler
            ->expects($this->once())
            ->method('onAuthenticationFailure');

        $this->refreshToken->refresh($this->createMock(Request::class));
    }

    private function setTtlUpdateOnRefreshToken(bool $ttlUpdate): void
    {
        $reflector = new \ReflectionClass(RefreshToken::class);
        $property = $reflector->getProperty('ttlUpdate');
        $property->setAccessible(true);
        $property->setValue($this->refreshToken, $ttlUpdate);
    }

    private function createAuthenticatorGetCredentialsExpectation(array $credentials): void
    {
        $this->authenticator
            ->expects($this->atLeastOnce())
            ->method('getCredentials')
            ->willReturn($credentials);
    }

    private function createAuthenticatorGetUserExpectation(UserInterface $user): void
    {
        $this->authenticator
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
    }

    private function createAuthenticatorCreateAuthenticatedTokenExpectation(
        PostAuthenticationGuardToken $postAuthenticationGuardToken
    ): void {
        $this->authenticator
            ->expects($this->once())
            ->method('createAuthenticatedToken')
            ->willReturn($postAuthenticationGuardToken);
    }
}
