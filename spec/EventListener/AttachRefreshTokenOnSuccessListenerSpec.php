<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\NameGenerator\NameGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator, RequestStack $requestStack, RequestRefreshToken $requestRefreshToken, NameGeneratorInterface $nameGenerator)
    {
        $ttl = 2592000;
        $this->beConstructedWith($refreshTokenManager, $ttl, $validator, $requestStack, $requestRefreshToken, $nameGenerator);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener');
    }

    public function it_attach_token_on_refresh(AuthenticationSuccessEvent $event, UserInterface $user, RequestStack $requestStack, RequestRefreshToken $requestRefreshToken, NameGeneratorInterface $nameGenerator)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);
        $nameGenerator->generateName('refresh_token')
                      ->willReturn('refresh_token');

        $refreshTokenArray = array('refresh_token' => 'thepreviouslyissuedrefreshtoken');
        $headers = new HeaderBag(array('content_type' => 'not-json'));
        $request = new Request();
        $request->headers = $headers;
        $request->request = new ParameterBag($refreshTokenArray);

        $requestStack->getCurrentRequest()->willReturn($request);

        $requestRefreshToken->getRefreshToken($request)->willReturn('thepreviouslyissuedrefreshtoken');

        $event->setData(Argument::exact($refreshTokenArray))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_token_on_credentials_auth(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager, $validator, RequestStack $requestStack)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);

        $headers = new HeaderBag(array('content_type' => 'not-json'));
        $request = new Request();
        $request->headers = $headers;
        $request->request = new ParameterBag();

        $requestStack->getCurrentRequest()->willReturn($request);

        $refreshTokenManager->create()->willReturn($refreshToken);

        $violationList = new ConstraintViolationList(array());
        $validator->validate($refreshToken)->willReturn($violationList);

        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $event->setData(Argument::any())->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_is_not_valid_user(AuthenticationSuccessEvent $event)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn(null);

        $this->attachRefreshToken($event);
    }

    public function it_should_support_name_generator_for_data_array_key(AuthenticationSuccessEvent $event, UserInterface $user, RequestStack $requestStack, RequestRefreshToken $requestRefreshToken, NameGeneratorInterface $nameGenerator)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);
        $nameGenerator->generateName('refresh_token')
                      ->willReturn('refreshToken');

        $refreshTokenArray = array('refreshToken' => 'thepreviouslyissuedrefreshtoken');
        $headers = new HeaderBag(array('content_type' => 'not-json'));
        $request = new Request();
        $request->headers = $headers;
        $request->request = new ParameterBag($refreshTokenArray);

        $requestStack->getCurrentRequest()->willReturn($request);

        $requestRefreshToken->getRefreshToken($request)->willReturn('thepreviouslyissuedrefreshtoken');

        $event->setData(Argument::exact($refreshTokenArray))->shouldBeCalled();

        $this->attachRefreshToken($event);
    }
}
