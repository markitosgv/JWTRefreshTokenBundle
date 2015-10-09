<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Provider;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

class RefreshTokenProviderSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $refreshToken->getUsername()->willReturn('testname');
        $this->beConstructedWith($refreshTokenManager);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider');
    }

    public function it_gets_username($refreshToken, $refreshTokenManager)
    {
        $refreshTokenManager->get(Argument::any())->willReturn($refreshToken);

        $this->getUsernameForRefreshToken(Argument::any())->shouldBe('testname');
    }

    public function it_not_gets_username($refreshTokenManager)
    {
        $refreshTokenManager->get(Argument::any())->willReturn(null);

        $this->getUsernameForRefreshToken(Argument::any())->shouldBe(null);
    }

    public function it_loads_by_username()
    {
        $this->loadUserByUsername('testname');
    }

    public function it_refresh_user(UserInterface $user)
    {
        $this->shouldThrow(new UnsupportedUserException())->duringRefreshUser($user);
    }

    public function it_supports_class()
    {
        $this->supportsClass('Symfony\Component\Security\Core\User\User')->shouldBe(true);
    }
}
