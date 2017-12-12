<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RefreshTokenAuthenticatorSpec extends ObjectBehavior
{
    public function let(RequestRefreshToken $requestRefreshToken)
    {
        $this->beConstructedWith($requestRefreshToken);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator');
    }

    public function it_supports_token(PreAuthenticatedToken $token, $providerKey)
    {
        $token->getProviderKey()->willReturn($providerKey);
        $this->supportsToken($token, $providerKey)->shouldBe(true);
    }

    public function it_creates_token(RequestRefreshToken $requestRefreshToken, Request $request)
    {
        $providerKey = 'api';

        // Stubs
        $requestRefreshToken->getRefreshToken($request)
            ->willReturn('arefreshtokenstring');

        $this->createToken($request, $providerKey)
            ->shouldBeLike(new PreAuthenticatedToken(
                '',
                'arefreshtokenstring',
                $providerKey
            ));
    }

    public function it_fails_on_authentication(Request $request, AuthenticationException $exception)
    {
        $this->onAuthenticationFailure($request, $exception)->shouldHaveType('Symfony\Component\HttpFoundation\Response');
    }
}
