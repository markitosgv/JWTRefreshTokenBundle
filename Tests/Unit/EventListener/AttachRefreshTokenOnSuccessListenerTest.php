<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class AttachRefreshTokenOnSuccessListenerTest extends TestCase
{
    const TTL = 2592000;
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private $refreshTokenManager;

    /**
     * @var MockObject|RequestStack
     */
    private $requestStack;

    /**
     * @var RefreshTokenGeneratorInterface|MockObject
     */
    private $refreshTokenGenerator;

    /**
     * @var ExtractorInterface|MockObject
     */
    private $extractor;

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
            []
        );
    }
    public function testAttachTokenOnRefresh()
    {
        /** @var UserInterface|MockObject $user */
        $user = $this->createMock(UserInterface::class);

        /** @var AuthenticationSuccessEvent|MockObject $event */
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

    public function testAttachTokenOnRefreshWithSingleUseToken()
    {
        $this->setSingleUseOnEventListener(true);

        /** @var AuthenticationSuccessEvent|MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        /** @var RefreshTokenInterface|MockObject $oldRefreshToken */
        $oldRefreshToken = $this->createMock(RefreshTokenInterface::class);

        /** @var RefreshTokenInterface|MockObject $newRefreshToken */
        $newRefreshToken = $this->createMock(RefreshTokenInterface::class);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

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

    public function testAttachTokenOnCredentialsAuth()
    {
        /** @var AuthenticationSuccessEvent|MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        /** @var UserInterface|MockObject $user */
        $user = $this->createMock(UserInterface::class);

        /** @var RefreshTokenInterface|MockObject $refreshToken */
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

    public function testDoesNothingWhenThereIsNotAUser()
    {
        /** @var AuthenticationSuccessEvent|MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        $event
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    private function setSingleUseOnEventListener(bool $singleUse): void
    {
        $reflector = new \ReflectionClass(AttachRefreshTokenOnSuccessListener::class);
        $property = $reflector->getProperty('singleUse');
        $property->setAccessible(true);
        $property->setValue($this->attachRefreshTokenOnSuccessListener, $singleUse);
    }
}
