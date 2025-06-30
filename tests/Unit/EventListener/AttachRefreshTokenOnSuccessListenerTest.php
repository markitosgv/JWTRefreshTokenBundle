<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

final class AttachRefreshTokenOnSuccessListenerTest extends TestCase
{
    public const TTL = 2592000;
    public const TOKEN_PARAMETER_NAME = 'refresh_token';
    public const RETURN_EXPIRATION = false;
    public const RETURN_EXPIRATION_PARAMETER_NAME = 'refresh_token_ttl';

    private MockObject&RefreshTokenManagerInterface $refreshTokenManager;

    private MockObject&RequestStack $requestStack;

    private MockObject&RefreshTokenGeneratorInterface $refreshTokenGenerator;

    private MockObject&ExtractorInterface $extractor;

    private AttachRefreshTokenOnSuccessListener $attachRefreshTokenOnSuccessListener;

    protected function setUp(): void
    {
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->refreshTokenGenerator = $this->createMock(RefreshTokenGeneratorInterface::class);
        $this->extractor = $this->createMock(ExtractorInterface::class);

        $this->attachRefreshTokenOnSuccessListener = new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            self::RETURN_EXPIRATION,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        );
    }

    public function testAttachesTheTokenToTheResponseBodyOnRefresh(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        $event
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event
            ->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        $event
            ->expects($this->atLeastOnce())
            ->method('setData')
            ->with($this->equalTo($refreshTokenArray));

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    public function testAddsTheTokenToTheResponseCookiesOnRefresh(): void
    {
        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        $event
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event
            ->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create('/', 'POST', $refreshTokenArray);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        $event
            ->expects($this->atLeastOnce())
            ->method('getResponse')
            ->willReturn(new Response());

        $event
            ->expects($this->atLeastOnce())
            ->method('setData')
            ->with($this->equalTo([]));

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            ['enabled' => true],
            self::RETURN_EXPIRATION,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        ))->attachRefreshToken($event);
    }

    public function testAttachTokenOnRefreshWithSingleUseToken(): void
    {
        $this->setSingleUseOnEventListener(true);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        /** @var RefreshTokenInterface&MockObject $oldRefreshToken */
        $oldRefreshToken = $this->createMock(RefreshTokenInterface::class);

        /** @var RefreshTokenInterface&MockObject $newRefreshToken */
        $newRefreshToken = $this->createMock(RefreshTokenInterface::class);

        $user = UserCreator::create();

        $event
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event
            ->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $request = Request::create('/', 'POST', [self::TOKEN_PARAMETER_NAME => $refreshTokenString]);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn($refreshTokenString);

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->willReturn($oldRefreshToken);

        $this->refreshTokenManager
            ->expects($this->atLeastOnce())
            ->method('delete')
            ->with($this->equalTo($oldRefreshToken));

        $newRefreshTokenString = 'thenewlyissuedrefreshtoken';

        $newRefreshToken
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn($newRefreshTokenString);

        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->with($user, self::TTL)
            ->willReturn($newRefreshToken);

        $this->refreshTokenManager
            ->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->equalTo($newRefreshToken));

        $event
            ->expects($this->atLeastOnce())
            ->method('setData')
            ->with($this->equalTo([self::TOKEN_PARAMETER_NAME => $newRefreshTokenString]));

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    public function testAttachesTheTokenToTheResponseBodyOnCredentialsAuth(): void
    {
        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        /** @var UserInterface&MockObject $user */
        $user = $this->createMock(UserInterface::class);

        /** @var RefreshTokenInterface&MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $event
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event
            ->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $request = Request::create('/', 'POST');
        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request, self::TOKEN_PARAMETER_NAME)
            ->willReturn(null);

        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->with($user, self::TTL)
            ->willReturn($refreshToken);

        $this->refreshTokenManager
            ->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->equalTo($refreshToken));

        $refreshToken
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('token');

        $event
            ->expects($this->atLeastOnce())
            ->method('setData')
            ->with($this->isType('array'));

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    private function setSingleUseOnEventListener(bool $singleUse): void
    {
        $this->attachRefreshTokenOnSuccessListener = new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            $singleUse,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            self::RETURN_EXPIRATION,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        );
    }
}
