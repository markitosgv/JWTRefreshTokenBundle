<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class RefreshTokenAuthenticatorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';
    /**
     * @var ExtractorInterface|MockObject
     */
    private $extractor;

    private RefreshTokenAuthenticator $refreshTokenAuthenticator;

    protected function setUp(): void
    {
        /** @var UserCheckerInterface|MockObject $userChecker */
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $this->extractor = $this->createMock(ExtractorInterface::class);

        $this->refreshTokenAuthenticator = new RefreshTokenAuthenticator(
            $userChecker,
            self::PARAMETER_NAME,
            $this->extractor
        );
    }

    public function testIsAGuardAuthenticator(): void
    {
        $this->assertInstanceOf(AbstractGuardAuthenticator::class, $this->refreshTokenAuthenticator);
    }

    public function testIsAnAuthenticationEntryPoint(): void
    {
        $this->assertInstanceOf(AuthenticationEntryPointInterface::class, $this->refreshTokenAuthenticator);
    }

    public function testReportsTheRequestAsSupportedWhenATokenIsPresent(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->createExtractorGetRefreshTokenExpectation($request, 'my-refresh-token');

        $this->assertTrue($this->refreshTokenAuthenticator->supports($request));
    }

    public function testReportsTheRequestAsNotSupportedWhenATokenIsNotPresent(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->createExtractorGetRefreshTokenExpectation($request, null);

        $this->assertFalse($this->refreshTokenAuthenticator->supports($request));
    }

    public function testFetchesTheCredentialsFromTheRequest(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $token = 'my-refresh-token';
        $this->createExtractorGetRefreshTokenExpectation($request, $token);

        $this->assertSame(['token' => $token], $this->refreshTokenAuthenticator->getCredentials($request));
    }

    public function testChecksForValidCredentials(): void
    {
        /** @var UserInterface|MockObject $user */
        $user = $this->createMock(UserInterface::class);
        $this->assertTrue($this->refreshTokenAuthenticator->checkCredentials([], $user));
    }

    public function testHandlesSuccessfulAuthentication(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        /** @var TokenInterface|MockObject $token */
        $token = $this->createMock(TokenInterface::class);

        $this->assertNull($this->refreshTokenAuthenticator->onAuthenticationSuccess($request, $token, 'firewall'));
    }

    public function testHandlesAnAuthenticationFailure(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var AuthenticationException|MockObject $exception */
        $exception = $this->createMock(AuthenticationException::class);

        $this->assertInstanceOf(
            Response::class,
            $this->refreshTokenAuthenticator->onAuthenticationFailure($request, $exception)
        );
    }

    public function testStartsAnAuthenticationRequest(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var AuthenticationException|MockObject $exception */
        $exception = $this->createMock(AuthenticationException::class);

        $this->assertInstanceOf(
            Response::class,
            $this->refreshTokenAuthenticator->start($request, $exception)
        );
    }

    public function testDoesNotSupportRememberMeAuthentication(): void
    {
        $this->assertFalse($this->refreshTokenAuthenticator->supportsRememberMe());
    }

    private function createExtractorGetRefreshTokenExpectation(Request $request, ?string $token): void
    {
        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request)
            ->willReturn($token);
    }
}
