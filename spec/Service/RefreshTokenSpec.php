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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RefreshTokenSpec extends ObjectBehavior
{
    public function let(RefreshTokenAuthenticator $authenticator, RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, RefreshTokenManagerInterface $refreshTokenManager, EventDispatcherInterface $eventDispatcher, ValidatorInterface $validator)
    {
        $ttl = 2592000;
        $ttlUpdate = false;
        $providerKey = 'testkey';
        $userIdentityField = 'Username';

        $eventDispatcher->dispatch(Argument::any(), Argument::any())->willReturn(Argument::any());

        $this->beConstructedWith($authenticator, $provider, $successHandler, $failureHandler, $refreshTokenManager, $ttl, $providerKey, $ttlUpdate, $userIdentityField, $eventDispatcher, $validator);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken');
    }

    public function it_refresh_token(Request $request, $refreshTokenManager, $authenticator, $token, PostAuthenticationGuardToken $postAuthenticationGuardToken, RefreshTokenInterface $refreshToken)
    {
        $authenticator->getCredentials(Argument::any())->willReturn(['token' => '1234']);
        $authenticator->getUser(Argument::any(), Argument::any())->willReturn(new User('test', 'test'));
        $authenticator->createAuthenticatedToken(Argument::any(), Argument::any())->willReturn($postAuthenticationGuardToken);
        $refreshTokenManager->get(Argument::any())->willReturn($refreshToken);
        $refreshToken->isValid()->willReturn(true);

        $this->refresh($request);
    }

    public function it_refresh_token_with_ttl_update(RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, Request $request, $refreshTokenManager, $authenticator, $token, PostAuthenticationGuardToken $postAuthenticationGuardToken, RefreshTokenInterface $refreshToken, EventDispatcherInterface $eventDispatcher, ValidatorInterface $validator)
    {
        $this->beConstructedWith($authenticator, $provider, $successHandler, $failureHandler, $refreshTokenManager, 2592000, 'testkey', true, '', $eventDispatcher, $validator);

        $authenticator->getCredentials(Argument::any())->willReturn(['token' => '1234']);
        $authenticator->getUser(Argument::any(), Argument::any())->willReturn(new User('test', 'test'));
        $authenticator->createAuthenticatedToken(Argument::any(), Argument::any())->willReturn($postAuthenticationGuardToken);

        $refreshTokenManager->get(Argument::any())->willReturn($refreshToken);
        $refreshToken->isValid()->willReturn(true);

        $refreshToken->setValid(Argument::any())->shouldBeCalled();
        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $this->refresh($request);
    }

    public function it_throws_an_authentication_exception(Request $request, $authenticator, PostAuthenticationGuardToken $postAuthenticationGuardToken, $failureHandler)
    {
        $authenticator->getCredentials(Argument::any())->willReturn(['token' => '1234']);
        $authenticator->getUser(Argument::any(), Argument::any())->willReturn(new User('test', 'test'));
        $authenticator->createAuthenticatedToken(Argument::any(), Argument::any())->willReturn($postAuthenticationGuardToken);

        $failureHandler->onAuthenticationFailure(Argument::any(), Argument::any())->shouldBeCalled();

        $this->refresh($request);
    }

    public function it_creates_a_refresh_token(RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, Request $request, RefreshTokenManagerInterface $refreshTokenManager, $authenticator, $token, PostAuthenticationGuardToken $postAuthenticationGuardToken, RefreshTokenInterface $refreshToken, EventDispatcherInterface $eventDispatcher, ValidatorInterface $validator, UserInterface $user, ConstraintViolationListInterface $errors)
    {
        $user->getUsername()->willReturn('testuser');

        $refreshTokenManager->create()->willReturn($refreshToken);
        $refreshToken->setUsername('testuser')->shouldBeCalled();
        $refreshToken->setRefreshToken()->shouldBeCalled();
        $refreshToken->setValid(Argument::type(\DateTime::class))->shouldBeCalled();
        $validator->validate($refreshToken)->willReturn($errors);
        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $this->create($user);
    }
}
