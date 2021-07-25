<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    const TTL = 2592000;
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    public function let(
        RefreshTokenManagerInterface $refreshTokenManager,
        RequestStack $requestStack,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        ExtractorInterface $extractor
    ) {
        $this->beConstructedWith($refreshTokenManager, self::TTL, $requestStack, self::TOKEN_PARAMETER_NAME, false, $refreshTokenGenerator, $extractor);
    }

    public function it_attach_token_on_refresh(
        RequestStack $requestStack,
        ExtractorInterface $extractor,
        AuthenticationSuccessEvent $event,
        UserInterface $user
    ) {
        $event->getUser()->willReturn($user);
        $event->getData()->willReturn([]);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => $refreshTokenString];
        $request = Request::create(
            '/',
            'POST',
            $refreshTokenArray
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $extractor->getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->willReturn($refreshTokenString);

        $event->setData($refreshTokenArray)->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_refresh_with_single_use_token(
        RefreshTokenManagerInterface $refreshTokenManager,
        RequestStack $requestStack,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        ExtractorInterface $extractor,
        AuthenticationSuccessEvent $event,
        RefreshTokenInterface $oldRefreshToken,
        RefreshTokenInterface $newRefreshToken
    ) {
        $this->beConstructedWith($refreshTokenManager, self::TTL, $requestStack, self::TOKEN_PARAMETER_NAME, true, $refreshTokenGenerator, $extractor);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $event->getUser()->willReturn($user);
        $event->getData()->willReturn([]);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $request = Request::create(
            '/',
            'POST',
            [self::TOKEN_PARAMETER_NAME => $refreshTokenString]
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $extractor->getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->willReturn($refreshTokenString);

        $refreshTokenManager->get($refreshTokenString)->willReturn($oldRefreshToken);
        $refreshTokenManager->delete($oldRefreshToken)->shouldBeCalled();

        $refreshTokenGenerator->createForUserWithTtl($user, self::TTL)->willReturn($newRefreshToken);

        $refreshTokenManager->save($newRefreshToken)->shouldBeCalled();

        $newRefreshTokenString = 'thenewlyissuedrefreshtoken';

        $newRefreshToken->getRefreshToken()->willReturn($newRefreshTokenString);

        $event->setData([self::TOKEN_PARAMETER_NAME => $newRefreshTokenString])->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_credentials_auth(
        RefreshTokenManagerInterface $refreshTokenManager,
        RequestStack $requestStack,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        ExtractorInterface $extractor,
        AuthenticationSuccessEvent $event,
        UserInterface $user,
        RefreshTokenInterface $refreshToken
    ) {
        $event->getUser()->willReturn($user);
        $event->getData()->willReturn([]);

        $request = Request::create(
            '/',
            'POST'
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $extractor->getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->willReturn(null);

        $refreshTokenGenerator->createForUserWithTtl($user, self::TTL)->willReturn($refreshToken);

        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $refreshToken->getRefreshToken()->willReturn(Argument::type('string'));

        $event->setData(Argument::type('array'))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_does_nothing_when_there_is_not_a_user(AuthenticationSuccessEvent $event)
    {
        $event->getUser()->willReturn(null);

        $this->attachRefreshToken($event);
    }
}
