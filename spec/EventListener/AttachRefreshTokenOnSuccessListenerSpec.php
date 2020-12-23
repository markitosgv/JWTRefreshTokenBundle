<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    public function let(RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack)
    {
        $ttl = 2592000;
        $userIdentityField = 'username';
        $singleUse = false;
        $this->beConstructedWith($refreshTokenManager, $ttl, $validator, $requestStack, $userIdentityField, self::TOKEN_PARAMETER_NAME, $singleUse);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener');
    }

    public function it_attach_token_on_refresh(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager, RequestStack $requestStack)
    {
        $event->getData()->willReturn([]);
        $event->getUser()->willReturn($user);

        $refreshTokenArray = [self::TOKEN_PARAMETER_NAME => 'thepreviouslyissuedrefreshtoken'];
        $headers = new HeaderBag(['content_type' => 'not-json']);
        $request = new Request();
        $request->headers = $headers;
        $request->request = new ParameterBag($refreshTokenArray);

        $requestStack->getCurrentRequest()->willReturn($request);

        $event->setData(Argument::exact($refreshTokenArray))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_credentials_auth(HeaderBag $headers, ParameterBag $requestBag, AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager, $validator, RequestStack $requestStack)
    {
        $event->getData()->willReturn([]);
        $event->getUser()->willReturn($user);

        $headers = new HeaderBag(['content_type' => 'not-json']);
        $request = new Request();
        $request->headers = $headers;
        $request->request = new ParameterBag();

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
