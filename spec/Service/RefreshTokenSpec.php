<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Service;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RefreshTokenSpec extends ObjectBehavior
{
    public function let(RefreshTokenAuthenticator $authenticator, RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, RefreshTokenManagerInterface $refreshTokenManager)
    {
        $ttl = 2592000;
        $ttlUpdate = false;
        $providerKey = 'testkey';

        $this->beConstructedWith($authenticator, $provider, $successHandler, $failureHandler, $refreshTokenManager, $ttl, $providerKey, $ttlUpdate);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(RefreshToken::class);
    }

    public function it_refresh_token(Request $request, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenAuthenticator $authenticator, TokenInterface $token, PreAuthenticatedToken $preAuthenticatedToken, RefreshTokenInterface $refreshToken)
    {
        $preAuthenticatedToken->getCredentials()->willReturn(Argument::type('string'));
        $authenticator->createToken(Argument::any(), Argument::any())->willReturn($token);
        $authenticator->authenticateToken(Argument::type(TokenInterface::class), Argument::type(UserProviderInterface::class), Argument::any())->willReturn($preAuthenticatedToken);

        $refreshTokenManager->get(Argument::type('string'))->willReturn($refreshToken);
        $refreshToken->isValid()->willReturn(true);

        $this->refresh($request);
    }

    public function it_refresh_token_with_ttl_update(RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, Request $request, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenAuthenticator $authenticator, TokenInterface $token, PreAuthenticatedToken $preAuthenticatedToken, RefreshTokenInterface $refreshToken)
    {
        $this->beConstructedWith($authenticator, $provider, $successHandler, $failureHandler, $refreshTokenManager, 2592000, 'testkey', true);

        $preAuthenticatedToken->getCredentials()->willReturn(Argument::type('string'));
        $authenticator->createToken(Argument::any(), Argument::any())->willReturn($token);
        $authenticator->authenticateToken(Argument::type(TokenInterface::class), Argument::type(UserProviderInterface::class), Argument::any())->willReturn($preAuthenticatedToken);

        $refreshTokenManager->get(Argument::type('string'))->willReturn($refreshToken);
        $refreshToken->isValid()->willReturn(true);

        $refreshToken->setValid(Argument::type(\DateTime::class))->shouldBeCalled();
        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $this->refresh($request);
    }

    public function it_throws_an_authentication_exception(Request $request, RefreshTokenAuthenticator $authenticator, RefreshTokenManagerInterface $refreshTokenManager, TokenInterface $token, PreAuthenticatedToken $preAuthenticatedToken, AuthenticationFailureHandler $failureHandler, RefreshTokenInterface $refreshToken)
    {
        $preAuthenticatedToken->getCredentials()->willReturn(Argument::type('string'));
        $authenticator->createToken(Argument::any(), Argument::any())->willReturn($token);
        $authenticator->authenticateToken(Argument::type(TokenInterface::class), Argument::type(UserProviderInterface::class), Argument::any())->willReturn($preAuthenticatedToken);

        $failureHandler->onAuthenticationFailure($request, Argument::type(AuthenticationException::class))->shouldBeCalled();

        $refreshToken->isValid()->willReturn(false);
        $refreshToken->getRefreshToken()->willReturn(Argument::type('string'));
        $refreshTokenManager->get(Argument::type('string'))->willReturn($refreshToken);


        $this->refresh($request);
    }
}
