<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Security\Core\User\UserInterface;

class AttachRefreshTokenOnSuccessListenerSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $ttl = 2592000;
        $this->beConstructedWith($refreshTokenManager, $ttl);
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

    public function it_attach_a_new_token(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn($user);

        $refreshTokenManager->getLastFromUsername(Argument::any())->willReturn(null);

        $refreshTokenManager->create()->willReturn($refreshToken);
        $refreshTokenManager->save($refreshToken)->shouldBeCalled();

        $event->setData(Argument::any())->shouldBeCalled();

        $this->attachRefreshToken($event);
    }

    public function it_is_not_valid_user(AuthenticationSuccessEvent $event, UserInterface $user, RefreshToken $refreshToken, $refreshTokenManager)
    {
        $event->getData()->willReturn(array());
        $event->getUser()->willReturn(null);

        $this->attachRefreshToken($event);
    }
}
