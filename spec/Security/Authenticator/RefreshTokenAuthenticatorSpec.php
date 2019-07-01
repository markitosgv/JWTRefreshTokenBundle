<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Authenticator;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;

class RefreshTokenAuthenticatorSpec extends ObjectBehavior
{
    public function let(UserCheckerInterface $userChecker)
    {
        $tokenParameterName = 'refresh_token';
        $this->beConstructedWith($userChecker, $tokenParameterName);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator');
    }

    public function it_get_credentials(Request $request)
    {
        $this->getCredentials($request)->shouldBeArray();
    }

    public function it_fails_on_authentication(Request $request, AuthenticationException $exception)
    {
        $this->onAuthenticationFailure($request, $exception)->shouldHaveType('Symfony\Component\HttpFoundation\Response');
    }
}
