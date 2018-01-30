<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RefreshTokenAuthenticatorSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(RefreshTokenAuthenticator::class);
    }

    public function it_supports_token(PreAuthenticatedToken $token, $providerKey)
    {
        $token->getProviderKey()->willReturn($providerKey);
        $this->supportsToken($token, $providerKey)->shouldBe(true);
    }

    public function it_fails_on_authentication(Request $request, AuthenticationException $exception)
    {
        $this->onAuthenticationFailure($request, $exception)->shouldHaveType(Response::class);
    }
}
