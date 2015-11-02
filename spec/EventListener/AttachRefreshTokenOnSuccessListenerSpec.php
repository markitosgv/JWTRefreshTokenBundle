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

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager, ValidatorInterface $validator)
    {
        $ttl = 2592000;
        $this->beConstructedWith($refreshTokenManager, $ttl, $validator);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener');
    }

    public function it_attach_an_existing_token(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);

        $refreshTokenManager->getLastFromUsername(Argument::any())->willReturn($refreshToken);

        $refreshToken->getRefreshToken()->willReturn(Argument::any());

        $event->setData(Argument::any())->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_attach_a_new_token(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager, $validator)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);

        $refreshTokenManager->getLastFromUsername(Argument::any())->willReturn(null);

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
}
