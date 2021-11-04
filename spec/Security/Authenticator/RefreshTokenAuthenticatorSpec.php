<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * @require Symfony\Component\Security\Guard\AbstractGuardAuthenticator
 */
class RefreshTokenAuthenticatorSpec extends ObjectBehavior
{
    private const PARAMETER_NAME = 'refresh_token';

    public function let(UserCheckerInterface $userChecker, ExtractorInterface $extractor): void
    {
        $this->beConstructedWith($userChecker, self::PARAMETER_NAME, $extractor);
    }

    public function it_is_a_guard_authenticator(): void
    {
        $this->shouldHaveType(AbstractGuardAuthenticator::class);
    }

    public function it_is_an_authentication_entry_point(): void
    {
        $this->shouldImplement(AuthenticationEntryPointInterface::class);
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

    public function it_fetches_the_credentials_from_the_request(ExtractorInterface $extractor, Request $request): void
    {
        $token = 'my-refresh-token';

        $extractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);

        $this->getCredentials($request)->shouldReturn(['token' => $token]);
    }

    public function it_checks_for_valid_credentials(UserInterface $user): void
    {
        $this->checkCredentials([], $user)->shouldReturn(true);
    }

    public function it_handles_successful_authentication(Request $request, TokenInterface $token): void
    {
        $this->onAuthenticationSuccess($request, $token, 'firewall')->shouldReturn(null);
    }

    public function it_handles_an_authentication_failure(Request $request, AuthenticationException $exception): void
    {
        $this->onAuthenticationFailure($request, $exception)->shouldHaveType(Response::class);
    }

    public function it_starts_an_authentication_request(Request $request, AuthenticationException $exception): void
    {
        $this->start($request, $exception)->shouldHaveType(Response::class);
    }

    public function it_does_not_support_remember_me_authentication(): void
    {
        $this->supportsRememberMe()->shouldReturn(false);
    }
}
