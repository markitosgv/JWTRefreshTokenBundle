<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    const TTL = 2592000;
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    public function let(RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack)
    {
        $this->beConstructedWith($refreshTokenManager, self::TTL, $validator, $requestStack, self::TOKEN_PARAMETER_NAME, false);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener');
    }

    public function it_attach_token_on_refresh(AuthenticationSuccessEvent $event, UserInterface $user, RefreshTokenInterface $refreshToken, RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack)
    {
        $event->getData()->willReturn([]);
        $event->getUser()->willReturn($user);

        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => 'thepreviouslyissuedrefreshtoken'];
        $request = Request::create(
            '/',
            'POST',
            $refreshTokenArray
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $event->setData(Argument::exact($refreshTokenArray))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_refresh_with_single_use_token(AuthenticationSuccessEvent $event, RefreshTokenInterface $oldRefreshToken, RefreshTokenInterface $newRefreshToken, RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack)
    {
        $this->beConstructedWith($refreshTokenManager, self::TTL, $validator, $requestStack, self::TOKEN_PARAMETER_NAME, true);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $event->getData()->willReturn([]);
        $event->getUser()->willReturn($user);

        $refreshTokenString = 'thepreviouslyissuedrefreshtoken';
        $request = Request::create(
            '/',
            'POST',
            [self::TOKEN_PARAMETER_NAME => $refreshTokenString]
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $refreshTokenManager->get($refreshTokenString)->willReturn($oldRefreshToken);
        $refreshTokenManager->delete($oldRefreshToken)->shouldBeCalled();
        $refreshTokenManager->create()->willReturn($newRefreshToken);

        $violationList = new ConstraintViolationList([]);
        $validator->validate($newRefreshToken)->willReturn($violationList);

        $refreshTokenManager->save($newRefreshToken)->shouldBeCalled();

        $newRefreshTokenString = 'thenewlyissuedrefreshtoken';

        $newRefreshToken->setUsername($username)->shouldBeCalled();
        $newRefreshToken->setRefreshToken()->shouldBeCalled();
        $newRefreshToken->setValid(Argument::type(\DateTime::class))->shouldBeCalled();
        $newRefreshToken->getRefreshToken()->willReturn($newRefreshTokenString);

        $event->setData(Argument::exact([self::TOKEN_PARAMETER_NAME => $newRefreshTokenString]))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_credentials_auth(HeaderBag $headers, ParameterBag $requestBag, AuthenticationSuccessEvent $event, UserInterface $user, RefreshTokenInterface $refreshToken, RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack)
    {
        $event->getData()->willReturn([]);
        $event->getUser()->willReturn($user);

        $request = Request::create(
            '/',
            'POST'
        );

        $requestStack->getCurrentRequest()->willReturn($request);

        $refreshTokenManager->create()->willReturn($refreshToken);

        $violationList = new ConstraintViolationList([]);
        $validator->validate($refreshToken)->willReturn($violationList);

        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $event->setData(Argument::any())->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_is_not_valid_user(AuthenticationSuccessEvent $event)
    {
        $event->getData()->willReturn([]);
        $event->getUser()->willReturn(null);

        $this->attachRefreshToken($event);
    }
}
