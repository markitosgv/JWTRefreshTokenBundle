<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as EntityRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The collaborators are built once in setUp(), so a test only sets expectations on the ones it
 * is about. The others stay mock objects without any.
 */
#[AllowMockObjectsWithoutExpectations]
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

    public function testDoesNothingWithoutACurrentRequest(): void
    {
        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $event->expects($this->never())->method('setData');

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    public function testTreatsAnEmptyExtractedTokenAsNoTokenAtAll(): void
    {
        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');
        $refreshToken->method('getValid')->willReturn(new DateTime('+1 day'));

        // An empty value must not be reused, a new token is generated instead
        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->willReturn($refreshToken);

        $event = $this->createEventExpectingData(
            [self::TOKEN_PARAMETER_NAME => 'thenewlyissuedrefreshtoken'],
            ''
        );

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    /**
     * Rotation on its own lets a user refresh for as long as they keep at it, since each token
     * arrives with a full ttl. Turning the update off ends the chain when the first token would
     * have expired.
     */
    public function testIssuesTheReplacementForWhatWasLeftOfTheSingleUseToken(): void
    {
        $expiresIn = 3600;

        /** @var RefreshTokenInterface&Stub $oldToken */
        $oldToken = $this->createStub(RefreshTokenInterface::class);
        $oldToken->method('getValid')->willReturn(new DateTime(sprintf('+%d seconds', $expiresIn)));

        $this->refreshTokenManager->method('get')->willReturn($oldToken);

        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->with($this->anything(), $this->callback(
                // Not the full ttl the listener is configured with, but what the old one had left
                static fn (int $ttl): bool => $ttl > $expiresIn - 10 && $ttl <= $expiresIn
            ))
            ->willReturn($newToken);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);

        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn('thepreviouslyissuedrefreshtoken');

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            true,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            false,
            self::RETURN_EXPIRATION_PARAMETER_NAME,
            false
        ))->attachRefreshToken($event);
    }

    /**
     * The authenticator has already loaded the token to authenticate with it, and Symfony puts the
     * security token in storage before the success handler runs, so looking it up again is a second
     * query for the same row.
     */
    public function testUsesTheTokenTheAuthenticatorAlreadyLoaded(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';

        /** @var RefreshTokenInterface&Stub $alreadyLoaded */
        $alreadyLoaded = $this->createStub(RefreshTokenInterface::class);
        $alreadyLoaded->method('getRefreshToken')->willReturn($refreshTokenString);
        $alreadyLoaded->method('getValid')->willReturn(new DateTime('+600 seconds'));

        $this->refreshTokenManager->expects($this->never())->method('get');

        $this->extractor->method('getRefreshToken')->willReturn($refreshTokenString);
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));

        $response = new Response();

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);
        $event->method('getResponse')->willReturn($response);

        $this->listenerReading($alreadyLoaded, true)->attachRefreshToken($event);

        $this->assertSame($alreadyLoaded->getValid()?->getTimestamp(), $response->headers->getCookies()[0]->getExpiresTime());
    }

    /**
     * A token left in storage by something other than this request is not the one to act on, so the
     * value decides rather than the type. Hashed storage never matches, which just means the lookup
     * happens as it did before.
     */
    public function testLooksTheTokenUpWhenTheOneInStorageIsNotTheOneAskedFor(): void
    {
        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';

        /** @var RefreshTokenInterface&Stub $somebodyElses */
        $somebodyElses = $this->createStub(RefreshTokenInterface::class);
        $somebodyElses->method('getRefreshToken')->willReturn('a-token-from-another-request');

        /** @var RefreshTokenInterface&Stub $theRightOne */
        $theRightOne = $this->createStub(RefreshTokenInterface::class);
        $theRightOne->method('getValid')->willReturn(new DateTime('+600 seconds'));

        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->with($refreshTokenString)
            ->willReturn($theRightOne);

        $this->extractor->method('getRefreshToken')->willReturn($refreshTokenString);
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);
        $event->method('getResponse')->willReturn(new Response());

        $this->listenerReading($somebodyElses, true)->attachRefreshToken($event);
    }

    private function listenerReading(RefreshTokenInterface $inStorage, bool $cookie): AttachRefreshTokenOnSuccessListener
    {
        // A real one rather than a stub: the class is final, and it is a value object anyway
        $securityToken = new PostRefreshTokenAuthenticationToken(
            $this->createStub(UserInterface::class),
            'api',
            [],
            $inStorage
        );

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($securityToken);

        return new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            ['enabled' => $cookie],
            false,
            self::RETURN_EXPIRATION_PARAMETER_NAME,
            true,
            null,
            $tokenStorage
        );
    }

    public function testRevokesTheOldestSessionsOnceTheUserIsOverTheLimit(): void
    {
        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($newToken);

        $user = $this->createStub(UserInterface::class);

        /** @var RefreshTokenManagerInterface&RevokeRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, RevokeRefreshTokenManagerInterface::class]);
        $manager
            ->expects($this->once())
            ->method('revokeAllButNewestForUser')
            ->with($user, 3)
            ->willReturn(1);

        $this->listenerWith($manager, 3)->attachRefreshToken($this->eventFor($user));
    }

    public function testLeavesTheSessionsAloneWithoutALimit(): void
    {
        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($newToken);

        /** @var RefreshTokenManagerInterface&RevokeRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, RevokeRefreshTokenManagerInterface::class]);
        $manager->expects($this->never())->method('revokeAllButNewestForUser');

        $this->listenerWith($manager, null)->attachRefreshToken($this->eventFor($this->createStub(UserInterface::class)));
    }

    /**
     * A limit that cannot be applied is worse than no limit, because it is believed.
     */
    public function testRefusesToPretendToLimitSessionsAManagerCannotRevoke(): void
    {
        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($newToken);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a refresh token manager implementing');

        $this->listenerWith($this->refreshTokenManager, 3)->attachRefreshToken($this->eventFor($this->createStub(UserInterface::class)));
    }

    /**
     * @param positive-int|null $maxTokensPerUser
     */
    private function listenerWith(RefreshTokenManagerInterface $manager, ?int $maxTokensPerUser): AttachRefreshTokenOnSuccessListener
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn(null);

        return new AttachRefreshTokenOnSuccessListener(
            $manager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            false,
            self::RETURN_EXPIRATION_PARAMETER_NAME,
            true,
            $maxTokensPerUser
        );
    }

    private function eventFor(UserInterface $user): AuthenticationSuccessEvent
    {
        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn([]);

        return $event;
    }

    /**
     * The cookie used to be given a ttl from now regardless of the token in it, which the browser
     * would then keep sending after the token behind it had expired.
     */
    public function testTheCookieExpiresWhenTheTokenInsideItDoes(): void
    {
        $expiresAt = new DateTime('+90 seconds');

        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');
        $newToken->method('getValid')->willReturn($expiresAt);

        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($newToken);

        $response = new Response();

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);
        $event->method('getResponse')->willReturn($response);

        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn(null);

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            ['enabled' => true],
            false,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        ))->attachRefreshToken($event);

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertSame($expiresAt->getTimestamp(), $cookies[0]->getExpiresTime());
    }

    public function testIssuesAFullTtlWithTheSingleUseUpdateLeftOn(): void
    {
        /** @var RefreshTokenInterface&Stub $oldToken */
        $oldToken = $this->createStub(RefreshTokenInterface::class);
        $oldToken->method('getValid')->willReturn(new DateTime('+60 seconds'));

        $this->refreshTokenManager->method('get')->willReturn($oldToken);

        /** @var RefreshTokenInterface&Stub $newToken */
        $newToken = $this->createStub(RefreshTokenInterface::class);
        $newToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->with($this->anything(), self::TTL)
            ->willReturn($newToken);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);

        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn('thepreviouslyissuedrefreshtoken');

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            true,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            false,
            self::RETURN_EXPIRATION_PARAMETER_NAME,
            true
        ))->attachRefreshToken($event);
    }

    public function testAttachesTheExpirationOfTheReusedToken(): void
    {
        $expiration = new DateTime('+1 day');

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getValid')->willReturn($expiration);

        $this->refreshTokenManager
            ->method('get')
            ->willReturn($refreshToken);

        $event = $this->createEventExpectingData([
            self::TOKEN_PARAMETER_NAME => 'thepreviouslyissuedrefreshtoken',
            self::RETURN_EXPIRATION_PARAMETER_NAME => $expiration->getTimestamp(),
        ]);

        $this->createListenerReturningExpiration()->attachRefreshToken($event);
    }

    public function testAttachesAZeroExpirationWhenTheTokenHasNoExpirationDate(): void
    {
        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getValid')->willReturn(null);

        $this->refreshTokenManager
            ->method('get')
            ->willReturn($refreshToken);

        $event = $this->createEventExpectingData([
            self::TOKEN_PARAMETER_NAME => 'thepreviouslyissuedrefreshtoken',
            self::RETURN_EXPIRATION_PARAMETER_NAME => 0,
        ]);

        $this->createListenerReturningExpiration()->attachRefreshToken($event);
    }

    public function testAttachesTheExpirationOfAFreshlyGeneratedToken(): void
    {
        $expiration = new DateTime('+1 day');

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getValid')->willReturn($expiration);
        $refreshToken->method('getRefreshToken')->willReturn('thenewlyissuedrefreshtoken');

        $this->refreshTokenGenerator
            ->expects($this->once())
            ->method('createForUserWithTtl')
            ->willReturn($refreshToken);

        $event = $this->createEventExpectingData([
            self::TOKEN_PARAMETER_NAME => 'thenewlyissuedrefreshtoken',
            self::RETURN_EXPIRATION_PARAMETER_NAME => $expiration->getTimestamp(),
        ], null);

        $this->createListenerReturningExpiration()->attachRefreshToken($event);
    }

    /**
     * @param array<string, mixed> $expectedData
     */
    private function createEventExpectingData(array $expectedData, ?string $extractedToken = 'thepreviouslyissuedrefreshtoken'): AuthenticationSuccessEvent&MockObject
    {
        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        $event->method('getUser')->willReturn($this->createMock(UserInterface::class));
        $event->method('getData')->willReturn([]);

        $request = Request::create('/', 'POST');

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn($extractedToken);

        $event
            ->expects($this->once())
            ->method('setData')
            ->with($this->equalTo($expectedData));

        return $event;
    }

    private function createListenerReturningExpiration(): AttachRefreshTokenOnSuccessListener
    {
        return new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            [],
            true,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        );
    }

    public function testAttachesTheTokenToTheResponseBodyOnRefresh(): void
    {
        /** @var UserInterface&Stub $user */
        $user = $this->createStub(UserInterface::class);

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
        /** @var UserInterface&Stub $user */
        $user = $this->createStub(UserInterface::class);

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

    /**
     * The token goes in an HttpOnly cookie the frontend cannot read, and the body keeps the
     * expiration so it still knows how long the refresh session lasts.
     */
    public function testKeepsTheExpirationInTheBodyWhenTheTokenMovesToACookie(): void
    {
        $expiration = new DateTime('+1 month');

        /** @var RefreshTokenInterface&Stub $refreshToken */
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $refreshToken->method('getValid')->willReturn($expiration);

        $this->refreshTokenManager->method('get')->willReturn($refreshToken);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);
        $event->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $event->method('getData')->willReturn([]);

        $response = new Response();
        $event->method('getResponse')->willReturn($response);

        $request = Request::create('/', 'POST');

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn('thepreviouslyissuedrefreshtoken');

        $event
            ->expects($this->once())
            ->method('setData')
            ->with($this->equalTo([self::RETURN_EXPIRATION_PARAMETER_NAME => $expiration->getTimestamp()]));

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $this->refreshTokenGenerator,
            $this->extractor,
            ['enabled' => true, 'http_only' => true, 'remove_token_from_body' => true],
            true,
            self::RETURN_EXPIRATION_PARAMETER_NAME
        ))->attachRefreshToken($event);

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertSame(self::TOKEN_PARAMETER_NAME, $cookies[0]->getName());
        $this->assertSame('thepreviouslyissuedrefreshtoken', $cookies[0]->getValue());
        $this->assertTrue($cookies[0]->isHttpOnly(), 'The token itself stays out of reach of the frontend');
    }

    public function testAttachTokenOnRefreshWithSingleUseToken(): void
    {
        $this->setSingleUseOnEventListener(true);

        /** @var AuthenticationSuccessEvent&MockObject $event */
        $event = $this->createMock(AuthenticationSuccessEvent::class);

        /** @var RefreshTokenInterface&Stub $oldRefreshToken */
        $oldRefreshToken = $this->createStub(RefreshTokenInterface::class);

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

        /** @var UserInterface&Stub $user */
        $user = $this->createStub(UserInterface::class);

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
            ->with($this->callback('is_array'));

        $this->attachRefreshTokenOnSuccessListener->attachRefreshToken($event);
    }

    public function testGivesAFreshlyIssuedTokenAFamilyOfItsOwn(): void
    {
        $issued = $this->issueOnLogin();

        $this->assertNotNull($issued->getFamily(), 'A login starts a chain, so its token belongs to one');
    }

    public function testStartsADifferentChainForEveryLogin(): void
    {
        $first = $this->issueOnLogin();
        $second = $this->issueOnLogin();

        $this->assertNotSame(
            $first->getFamily(),
            $second->getFamily(),
            'Two logins are two sessions; ending one must not be able to end the other'
        );
    }

    /**
     * The point of the family: the token handed out on a refresh belongs to the same chain as the
     * one it replaced, so a session survives being refreshed while staying one session.
     */
    public function testCarriesTheFamilyOverToTheReplacementToken(): void
    {
        $replaced = new EntityRefreshToken();
        $replaced->setRefreshToken('thepreviouslyissuedrefreshtoken');
        $replaced->setValid(new DateTime('+600 seconds'));
        $replaced->setFamily('the-chain-this-session-started-as');

        $issued = $this->issueOnRefreshReplacing($replaced);

        $this->assertSame('the-chain-this-session-started-as', $issued->getFamily());
    }

    /**
     * A token class of an application's own need not have families. The replacement then starts a
     * chain rather than the listener reaching for a method that is not there.
     */
    public function testStartsAChainWhenTheTokenBeingReplacedHasNoFamilies(): void
    {
        /** @var RefreshTokenInterface&Stub $replaced */
        $replaced = $this->createStub(RefreshTokenInterface::class);
        $replaced->method('getRefreshToken')->willReturn('thepreviouslyissuedrefreshtoken');
        $replaced->method('getValid')->willReturn(new DateTime('+600 seconds'));

        $this->assertNotNull($this->issueOnRefreshReplacing($replaced)->getFamily());
    }

    /**
     * Refreshing a token the storage has never heard of leaves nothing to carry on from, so the
     * replacement starts its own chain rather than being left without one.
     */
    public function testStartsAChainWhenTheTokenBeingRefreshedIsUnknown(): void
    {
        $issued = new EntityRefreshToken();
        $issued->setRefreshToken('thenewlyissuedrefreshtoken');

        $this->refreshTokenManager->method('get')->willReturn(null);
        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($issued);
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn('atokennobodyissued');

        $this->singleUseListener()->attachRefreshToken($this->eventFor($this->createStub(UserInterface::class)));

        $this->assertNotNull($issued->getFamily());
    }

    /**
     * Runs one login through the listener and hands back the token it issued.
     */
    private function issueOnLogin(): EntityRefreshToken
    {
        $issued = new EntityRefreshToken();
        $issued->setRefreshToken('thenewlyissuedrefreshtoken');

        $generator = $this->createStub(RefreshTokenGeneratorInterface::class);
        $generator->method('createForUserWithTtl')->willReturn($issued);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));

        $extractor = $this->createStub(ExtractorInterface::class);
        $extractor->method('getRefreshToken')->willReturn(null);

        (new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $requestStack,
            self::TOKEN_PARAMETER_NAME,
            false,
            $generator,
            $extractor,
            []
        ))->attachRefreshToken($this->eventFor($this->createStub(UserInterface::class)));

        return $issued;
    }

    /**
     * Runs one single-use refresh of the given token through the listener and hands back the token
     * issued in its place.
     */
    private function issueOnRefreshReplacing(RefreshTokenInterface $replaced): EntityRefreshToken
    {
        $issued = new EntityRefreshToken();
        $issued->setRefreshToken('thenewlyissuedrefreshtoken');

        $this->refreshTokenManager->method('get')->willReturn($replaced);
        $this->refreshTokenGenerator->method('createForUserWithTtl')->willReturn($issued);
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('/', 'POST'));
        $this->extractor->method('getRefreshToken')->willReturn('thepreviouslyissuedrefreshtoken');

        $this->singleUseListener()->attachRefreshToken($this->eventFor($this->createStub(UserInterface::class)));

        return $issued;
    }

    private function singleUseListener(): AttachRefreshTokenOnSuccessListener
    {
        return new AttachRefreshTokenOnSuccessListener(
            $this->refreshTokenManager,
            self::TTL,
            $this->requestStack,
            self::TOKEN_PARAMETER_NAME,
            true,
            $this->refreshTokenGenerator,
            $this->extractor,
            []
        );
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
