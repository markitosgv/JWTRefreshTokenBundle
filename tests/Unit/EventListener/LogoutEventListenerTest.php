<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\EventListener\LogoutEventListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutEventListenerTest extends TestCase
{
    public const string TOKEN_PARAMETER_NAME = 'refresh_token';

    private MockObject&RefreshTokenManagerInterface $refreshTokenManager;

    private MockObject&ExtractorInterface $extractor;

    protected function setUp(): void
    {
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->extractor = $this->createMock(ExtractorInterface::class);
    }

    public function testInvalidatesTheTokenOfTheUserLoggingOut(): void
    {
        $request = Request::create('/', 'POST');

        $authenticated = $this->createStub(TokenInterface::class);
        $authenticated->method('getUserIdentifier')->willReturn('someone');

        $event = new LogoutEvent($request, $authenticated);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('a-token-of-their-own');

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getUsername')->willReturn('someone');

        $this->refreshTokenManager->method('get')->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($refreshToken));

        (new LogoutEventListener($this->refreshTokenManager, $this->extractor, self::TOKEN_PARAMETER_NAME, []))
            ->onLogout($event);

        $this->assertStringContainsString('has been invalidated', (string) $event->getResponse()?->getContent());
    }

    /**
     * Answered as a token that does not exist, so the endpoint cannot be asked whether somebody
     * else's token is still live.
     */
    public function testRefusesToInvalidateTheTokenOfAnotherUser(): void
    {
        $request = Request::create('/', 'POST');

        $authenticated = $this->createStub(TokenInterface::class);
        $authenticated->method('getUserIdentifier')->willReturn('someone');

        $event = new LogoutEvent($request, $authenticated);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('a-token-of-somebody-else');

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getUsername')->willReturn('somebody-else');

        $this->refreshTokenManager->method('get')->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->never())
            ->method('delete');

        (new LogoutEventListener($this->refreshTokenManager, $this->extractor, self::TOKEN_PARAMETER_NAME, []))
            ->onLogout($event);

        $this->assertStringContainsString('already invalid', (string) $event->getResponse()?->getContent(), 'The answer should not say the token exists');
    }

    public function testInvalidatesTokenAndClearsCookieFromResponse(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($refreshToken));

        $listener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            [
                'enabled' => true,
            ]
        );
        $listener->onLogout($event);

        /** @var JsonResponse|null $response */
        $response = $event->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"code":200,"message":"The supplied refresh_token has been invalidated."}', $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        // The cookie is named after the token parameter, so clearing it is clearing the one that
        // was set, and both follow the configured name rather than a name of their own
        $this->assertSame(self::TOKEN_PARAMETER_NAME, $cookies[0]->getName());
        $this->assertLessThan(time(), $cookies[0]->getExpiresTime(), 'A cleared cookie has already expired');
    }

    public function testInvalidatesTokenAndDoesNotClearCookieFromResponseWhenCookieSupportIsDisabled(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($refreshToken));

        $listener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            [
                'enabled' => false,
            ]
        );
        $listener->onLogout($event);

        /** @var JsonResponse|null $response */
        $response = $event->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"code":200,"message":"The supplied refresh_token has been invalidated."}', $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->assertCount(0, $response->headers->getCookies(), 'Nothing set the cookie, so nothing should clear it');
    }

    public function testCreatesASuccessResponseWhenTheRefreshTokenIsAlreadyInvalid(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->refreshTokenManager
            ->expects($this->never())
            ->method('delete');

        $listener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            [
                'enabled' => false,
            ]
        );
        $listener->onLogout($event);

        /** @var JsonResponse|null $response */
        $response = $event->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"code":200,"message":"The supplied refresh_token is already invalid."}', $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCreatesAnErrorResponseWhenTheRefreshTokenIsNotInTheRequest(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn(null);

        $this->refreshTokenManager
            ->expects($this->never())
            ->method('get');

        $listener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            [
                'enabled' => false,
            ]
        );
        $listener->onLogout($event);

        /** @var JsonResponse|null $response */
        $response = $event->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"code":400,"message":"No refresh_token found."}', $response->getContent());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
