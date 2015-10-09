<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Service;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RefreshTokenSpec extends ObjectBehavior
{
    public function let(RefreshTokenAuthenticator $authenticator, RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, RefreshTokenManagerInterface $refreshTokenManager, TokenInterface $token, UserProviderInterface $userProvider, $ttl, $providerKey)
    {
        $ttl = 2592000;
        $providerKey = 'testkey';

        $this->beConstructedWith($authenticator, $provider, $successHandler, $failureHandler, $refreshTokenManager, $ttl, $providerKey);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken');
    }

    public function it_refresh_token(Request $request, $refreshTokenManager, $authenticator, $token, PreAuthenticatedToken $preAuthenticatedToken, RefreshTokenInterface $refreshToken)
    {
        $authenticator->createToken(Argument::any(), Argument::any())->willReturn($token);
        $authenticator->authenticateToken(Argument::any(), Argument::any(), Argument::any())->willReturn($preAuthenticatedToken);

        $refreshTokenManager->get(Argument::any())->willReturn($refreshToken);
        $refreshToken->isValid()->willReturn(true);
        $refreshToken->setValid(Argument::any())->shouldBeCalled();
        $refreshTokenManager->save(Argument::any())->shouldBeCalled();

        $this->refresh($request);
    }

    public function it_throws_an_authentication_exception(Request $request, $refreshTokenManager, $authenticator, $token, PreAuthenticatedToken $preAuthenticatedToken, RefreshTokenInterface $refreshToken, $failureHandler)
    {
        $authenticator->createToken(Argument::any(), Argument::any())->willReturn($token);
        $authenticator->authenticateToken(Argument::any(), Argument::any(), Argument::any())->willReturn($preAuthenticatedToken);

        $failureHandler->onAuthenticationFailure(Argument::any(), Argument::any())->shouldBeCalled();

        $this->refresh($request);
    }
}
