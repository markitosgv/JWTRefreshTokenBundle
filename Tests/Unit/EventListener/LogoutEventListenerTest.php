<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\EventListener\LogoutEventListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutEventListenerTest extends TestCase
{
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private $refreshTokenManager;

    /**
     * @var ExtractorInterface|MockObject
     */
    private $extractor;

    private LogoutEventListener $eventListener;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(LogoutEvent::class)) {
            self::markTestSkipped('Only applies to Symfony 5.3+');
        }
    }

    protected function setUp(): void
    {
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->extractor = $this->createMock(ExtractorInterface::class);

        $this->eventListener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            []
        );
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

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

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

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

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

    /**
     * @group legacy
     */
    public function testInvalidatesTokenAndClearsCookieFromResponseWhenFirewallContextIsConfigured(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);
        $request->attributes->set('_firewall_context', 'security.firewall.map.context.api');

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

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
            ],
            'security.firewall.map.context.api'
        );
        $listener->onLogout($event);

        /** @var JsonResponse|null $response */
        $response = $event->getResponse();

        $this->assertNotNull($response);

        $this->assertSame('{"code":200,"message":"The supplied refresh_token has been invalidated."}', $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @group legacy
     */
    public function testDoesNotInvalidateTokenWhenEventIsEmittedFromUnsupportedFirewallContext(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);
        $request->attributes->set('_firewall_context', 'security.firewall.map.context.api');

        $event = new LogoutEvent($request, null);

        $this->extractor
            ->expects($this->never())
            ->method('getRefreshToken');

        $this->refreshTokenManager
            ->expects($this->never())
            ->method('get');

        $listener = new LogoutEventListener(
            $this->refreshTokenManager,
            $this->extractor,
            self::TOKEN_PARAMETER_NAME,
            [
                'enabled' => true,
            ],
            'security.firewall.map.context.main'
        );
        $listener->onLogout($event);

        $this->assertNull($event->getResponse());
    }
}