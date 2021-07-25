<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
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
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @require Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface
 */
final class RefreshTokenAuthenticatorSpec extends ObjectBehavior
{
    private const PARAMETER_NAME = 'refresh_token';

    public function let(
        RefreshTokenManagerInterface $refreshTokenManager,
        EventDispatcherInterface $eventDispatcher,
        ExtractorInterface $extractor,
        UserProviderInterface $userProvider,
        AuthenticationSuccessHandlerInterface $successHandler,
        AuthenticationFailureHandlerInterface $failureHandler
    ): void {
        $this->beConstructedWith($refreshTokenManager, $eventDispatcher, $extractor, $userProvider, $successHandler, $failureHandler, []);
    }

    public function it_is_an_authenticator(): void
    {
        $this->shouldHaveType(AuthenticatorInterface::class);
    }

    public function it_is_an_authentication_entry_point(): void
    {
        $this->shouldHaveType(AuthenticationEntryPointInterface::class);
    }

    public function it_reports_the_request_as_supported_when_a_token_is_present(ExtractorInterface $extractor, Request $request): void
    {
        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $this->supports($request)->shouldReturn(true);
    }

    public function it_reports_the_request_as_not_supported_when_a_token_is_not_present(ExtractorInterface $extractor, Request $request): void
    {
        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);

        $this->supports($request)->shouldReturn(false);
    }

    public function it_authenticates_the_request_when_ttl_update_is_disabled(ExtractorInterface $extractor, Request $request, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken): void
    {
        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $refreshTokenManager->get($token)->willReturn($refreshToken);

        $refreshToken->isValid()->willReturn(true);
        $refreshToken->getUsername()->willReturn('test@example.com');

        $this->authenticate($request)->shouldImplement(PassportInterface::class);
    }

    public function it_authenticates_the_request_when_ttl_update_is_enabled(
        RefreshTokenManagerInterface $refreshTokenManager,
        EventDispatcherInterface $eventDispatcher,
        ExtractorInterface $extractor,
        UserProviderInterface $userProvider,
        AuthenticationSuccessHandlerInterface $successHandler,
        AuthenticationFailureHandlerInterface $failureHandler,
        Request $request,
        RefreshTokenInterface $refreshToken
    ): void {
        $this->beConstructedWith($refreshTokenManager, $eventDispatcher, $extractor, $userProvider, $successHandler, $failureHandler, ['ttl_update' => true]);

        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $refreshTokenManager->get($token)->willReturn($refreshToken);

        $refreshToken->isValid()->willReturn(true);
        $refreshToken->setValid(Argument::type(\DateTimeInterface::class))->shouldBeCalled();

        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $refreshToken->getUsername()->willReturn('test@example.com');

        $this->authenticate($request)->shouldImplement(PassportInterface::class);
    }

    public function it_does_not_authenticate_the_request_when_the_token_is_not_valid(ExtractorInterface $extractor, Request $request, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken): void
    {
        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $refreshTokenManager->get($token)->willReturn($refreshToken);

        $refreshToken->isValid()->willReturn(false);
        $refreshToken->getRefreshToken()->willReturn($token);

        $this->shouldThrow(InvalidTokenException::class)->duringAuthenticate($request);
    }

    public function it_does_not_authenticate_the_request_when_the_token_is_not_found_in_storage(ExtractorInterface $extractor, Request $request, RefreshTokenManagerInterface $refreshTokenManager): void
    {
        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $refreshTokenManager->get($token)->willReturn(null);

        $this->shouldThrow(TokenNotFoundException::class)->duringAuthenticate($request);
    }

    public function it_does_not_authenticate_the_request_when_the_token_is_not_found_in_the_request(ExtractorInterface $extractor, Request $request): void
    {
        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);

        $this->shouldThrow(MissingTokenException::class)->duringAuthenticate($request);
    }

    public function it_creates_the_security_token(): void
    {
        $refreshToken = new class() extends AbstractRefreshToken {};

        $userIdentifier = 'test@example.com';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($userIdentifier, $password);
        } else {
            $user = new User($userIdentifier, $password);
        }

        $passport = new SelfValidatingPassport(
            new UserBadge(
                $userIdentifier,
                static function (string $userIdentifier) use ($user): UserInterface {
                    return $user;
                }
            )
        );
        $passport->setAttribute('refreshToken', $refreshToken);

        $this->createAuthenticatedToken($passport, 'test')->shouldImplement(TokenInterface::class);
    }

    public function it_does_not_create_the_security_token_when_the_passport_does_not_have_the_refresh_token(): void
    {
        $userIdentifier = 'test@example.com';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($userIdentifier, $password);
        } else {
            $user = new User($userIdentifier, $password);
        }

        $passport = new SelfValidatingPassport(
            new UserBadge(
                $userIdentifier,
                static function (string $userIdentifier) use ($user): UserInterface {
                    return $user;
                }
            )
        );

        $this->shouldThrow(LogicException::class)->duringCreateAuthenticatedToken($passport, 'test');
    }

    public function it_does_not_create_the_security_token_when_the_passport_does_not_implement_the_required_interface(PassportInterface $passport): void
    {
        $this->shouldThrow(LogicException::class)->duringCreateAuthenticatedToken($passport, 'test');
    }

    public function it_handles_successful_authentication(AuthenticationSuccessHandlerInterface $successHandler, Request $request, TokenInterface $token): void
    {
        $successHandler->onAuthenticationSuccess($request, $token)->willReturn(null);

        $this->onAuthenticationSuccess($request, $token, 'test')->shouldReturn(null);
    }

    public function it_handles_an_authentication_failure(AuthenticationFailureHandlerInterface $failureHandler, Request $request, AuthenticationException $exception): void
    {
        $failureHandler->onAuthenticationFailure($request, $exception)->willReturn(null);

        $this->onAuthenticationFailure($request, $exception)->shouldReturn(null);
    }

    public function it_starts_an_authentication_request(EventDispatcherInterface $eventDispatcher, Request $request, AuthenticationException $exception): void
    {
        $eventDispatcher->dispatch(Argument::any(), Argument::any())->willReturnArgument(0);

        $exception->getMessageKey()->willReturn('Testing Failure');

        $this->start($request, $exception)->shouldBeAnInstanceOf(RefreshAuthenticationFailureResponse::class);
    }
}
