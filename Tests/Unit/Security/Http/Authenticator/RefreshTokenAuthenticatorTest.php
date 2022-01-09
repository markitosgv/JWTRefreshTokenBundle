<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Http\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RefreshTokenAuthenticatorTest extends TestCase
{
    /**
     * @var RefreshTokenManagerInterface|MockObject
     */
    private $refreshTokenManager;

    /**
     * @var ExtractorInterface|MockObject
     */
    private $extractor;

    /**
     * @var MockObject|AuthenticationSuccessHandlerInterface
     */
    private $successHandler;

    /**
     * @var MockObject|AuthenticationFailureHandlerInterface
     */
    private $failureHandler;

    private RefreshTokenAuthenticator $refreshTokenAuthenticator;

    protected function setUp(): void
    {
        $this->refreshTokenManager = $this->createMock(RefreshTokenManagerInterface::class);
        $this->extractor = $this->createMock(ExtractorInterface::class);
        $this->successHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);

        $this->refreshTokenAuthenticator = new RefreshTokenAuthenticator(
            $this->refreshTokenManager,
            $this->createMock(EventDispatcherInterface::class),
            $this->extractor,
            $this->createMock(UserProviderInterface::class),
            $this->successHandler,
            $this->failureHandler,
            []
        );
    }

    public function testAnAuthenticator(): void
    {
        $this->assertInstanceOf(AuthenticatorInterface::class, $this->refreshTokenAuthenticator);
    }

    public function testAnAuthenticationEntryPoint(): void
    {
        $this->assertInstanceOf(AuthenticationEntryPointInterface::class, $this->refreshTokenAuthenticator);
    }

    public function testReportsTheRequestAsSupportedWhenATokenIsPresent(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $token = 'my-refresh-token';
        $this->setExtractorGetRefreshTokenExpectation($request, $token);

        $this->assertTrue($this->refreshTokenAuthenticator->supports($request));
    }

    public function testReportsTheRequestAsNotSupportedWhenATokenIsNotPresent(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->setExtractorGetRefreshTokenExpectation($request, null);
        $this->assertFalse($this->refreshTokenAuthenticator->supports($request));
    }

    public function testAuthenticatesTheRequestWhenTtlUpdateIsDisabled(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $token = 'my-refresh-token';

        $this->setExtractorGetRefreshTokenExpectation($request, $token);
        $this->setRefreshTokenManagerGetExpectation($token, $refreshToken);
        $this->setRefreshTokenIsValidExpectation($refreshToken, true);
        $this->setRefreshTokenGetUsernameExpectation($refreshToken, 'test@example.com');

        $passport = $this->refreshTokenAuthenticator->authenticate($request);
        $this->assertInstanceOf(PassportInterface::class, $passport);
    }

    public function testAuthenticatesTheRequestWhenTtlUpdateIsEnabled(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);

        $this->appendOptionsOnRefreshTokenAuthenticator(['ttl_update' => true]);

        $token = 'my-refresh-token';

        $this->setExtractorGetRefreshTokenExpectation($request, $token);
        $this->setRefreshTokenManagerGetExpectation($token, $refreshToken);
        $this->setRefreshTokenIsValidExpectation($refreshToken, true);
        $this->setRefreshTokenGetUsernameExpectation($refreshToken, 'test@example.com');

        $refreshToken
            ->expects($this->atLeastOnce())
            ->method('setValid')
            ->with($this->isInstanceOf(\DateTimeInterface::class));

        $this->refreshTokenManager
            ->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->equalTo($refreshToken));

        $this->assertInstanceOf(Passport::class, $this->refreshTokenAuthenticator->authenticate($request));
    }

    public function testDoesNotAuthenticateTheRequestWhenTheTokenIsNotValid(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var RefreshTokenInterface|MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $token = 'my-refresh-token';

        $this->setExtractorGetRefreshTokenExpectation($request, $token);
        $this->setRefreshTokenManagerGetExpectation($token, $refreshToken);
        $this->setRefreshTokenIsValidExpectation($refreshToken, false);

        $refreshToken
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn($token);

        $this->expectExceptionObject(
            new InvalidTokenException('Refresh token "my-refresh-token" is invalid.')
        );

        $this->refreshTokenAuthenticator->authenticate($request);
    }

    public function testDoesNotAuthenticateTheRequestWhenTheTokenIsNotFoundInStorage(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $token = 'my-refresh-token';

        $this->setExtractorGetRefreshTokenExpectation($request, $token);
        $this->setRefreshTokenManagerGetExpectation($token, null);

        $this->expectExceptionObject(new TokenNotFoundException());

        $this->refreshTokenAuthenticator->authenticate($request);
    }

    public function testDoesNotAuthenticateTheRequestWhenTheTokenIsNotFoundInTheRequest(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->setExtractorGetRefreshTokenExpectation($request, null);

        $this->expectExceptionObject(new MissingTokenException());

        $this->refreshTokenAuthenticator->authenticate($request);
    }


    public function testCreatesTheAuthenticatedToken(): void
    {
        $userIdentifier = 'test@example.com';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($userIdentifier, $password);
        } else {
            $user = new User($userIdentifier, $password);
        }

        $passport = $this->createUserPassport($userIdentifier, $user);
        $passport->setAttribute('refreshToken', $this->createMock(RefreshTokenInterface::class));

        $token = $this->refreshTokenAuthenticator->createAuthenticatedToken($passport, 'test');
        $this->assertInstanceOf(TokenInterface::class, $token);
    }

    public function testDoesNotCreateTheAuthenticatedTokenWhenThePassportDoesNotImplementTheRequiredInterface(): void
    {
        /** @var PassportInterface|MockObject $passport */
        $passport = $this->createMock(PassportInterface::class);

        $this->expectException(LogicException::class);

        $this->refreshTokenAuthenticator->createAuthenticatedToken($passport, 'test');
    }

    public function testCreatesTheToken(): void
    {
        $userIdentifier = 'test@example.com';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($userIdentifier, $password);
        } else {
            $user = new User($userIdentifier, $password);
        }

        $passport = $this->createUserPassport($userIdentifier, $user);
        $passport->setAttribute('refreshToken', $this->createMock(RefreshTokenInterface::class));

        $token = $this->refreshTokenAuthenticator->createAuthenticatedToken($passport, 'test');
        $this->assertInstanceOf(TokenInterface::class, $token);
    }

    public function testDoesNotCreateTheTokenWhenThePassportDoesNotHaveTheRefreshToken(): void
    {
        $userIdentifier = 'test@example.com';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($userIdentifier, $password);
        } else {
            $user = new User($userIdentifier, $password);
        }

        $passport = $this->createUserPassport($userIdentifier, $user);

        $this->expectException(LogicException::class);

        $this->refreshTokenAuthenticator->createToken($passport, 'test');
    }

    public function testHandlesSuccessfulAuthentication(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var TokenInterface|MockObject $token */
        $token = $this->createMock(TokenInterface::class);

        $this->successHandler
            ->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn(null);

        $this->assertNull(
            $this->refreshTokenAuthenticator->onAuthenticationSuccess($request, $token, 'test')
        );
    }

    public function testHandlesAnAuthenticationFailure(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var AuthenticationException|MockObject $exception */
        $exception = $this->createMock(AuthenticationException::class);

        $this->failureHandler
            ->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($request, $exception)
            ->willReturn(null);

        $this->assertNull($this->refreshTokenAuthenticator->onAuthenticationFailure($request, $exception));
    }

    public function testStartsAnAuthenticationRequest(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        /** @var AuthenticationException|MockObject $exception */
        $exception = $this->createMock(AuthenticationException::class);

        $exception
            ->expects($this->once())
            ->method('getMessageKey')
            ->willReturn('Testing Failure');

        $response = $this->refreshTokenAuthenticator->start($request, $exception);
        $this->assertInstanceOf(RefreshAuthenticationFailureResponse::class, $response);
    }

    private function setExtractorGetRefreshTokenExpectation(Request $request, ?string $token): void
    {
        $this->extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->with($request)
            ->willReturn($token);
    }

    private function setRefreshTokenManagerGetExpectation(string $token, ?RefreshTokenInterface $refreshToken): void
    {
        $this->refreshTokenManager
            ->expects($this->once())
            ->method('get')
            ->with($token)
            ->willReturn($refreshToken);
    }

    private function setRefreshTokenIsValidExpectation(MockObject $refreshToken, bool $isValid): void
    {
        $refreshToken
            ->expects($this->once())
            ->method('isValid')
            ->willReturn($isValid);
    }

    private function setRefreshTokenGetUsernameExpectation(MockObject $refreshToken, string $username): void
    {
        $refreshToken
            ->expects($this->once())
            ->method('getUsername')
            ->willReturn($username);
    }

    private function appendOptionsOnRefreshTokenAuthenticator(array $options): void
    {
        $reflector = new \ReflectionClass(RefreshTokenAuthenticator::class);
        $property = $reflector->getProperty('options');
        $property->setAccessible(true);

        $property->setValue(
            $this->refreshTokenAuthenticator,
            array_merge(
                $property->getValue($this->refreshTokenAuthenticator),
                $options
            )
        );
    }

    private function createUserPassport(string $userIdentifier, UserInterface $user): SelfValidatingPassport
    {
        return new SelfValidatingPassport(
            new UserBadge(
                $userIdentifier,
                function (string $userIdentifier) use ($user) : UserInterface {
                    return $user;
                }
            )
        );
    }
}
