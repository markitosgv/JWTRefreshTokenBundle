<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;

class RefreshTokenSpec extends ObjectBehavior
{
    public function let()
    {
        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->beConstructedThrough('createForUserWithTtl', ['token', $user, 600]);
    }

    public function it_is_a_refresh_token()
    {
        $this->shouldImplement(RefreshTokenInterface::class);
    }

    public function it_has_a_custom_refresh_token()
    {
        $this->setRefreshToken('custom-token')->shouldReturn($this);
        $this->getRefreshToken()->shouldReturn('custom-token');
    }

    public function it_generates_a_refresh_token()
    {
        $this->setRefreshToken(null)->shouldReturn($this);
        $this->getRefreshToken()->shouldBeString();
    }

    public function it_has_username()
    {
        $this->getUsername()->shouldReturn('username');
    }

    public function it_has_a_valid_timestamp()
    {
        $this->getValid()->shouldBeAnInstanceOf(\DateTimeInterface::class);
    }

    public function it_is_valid()
    {
        $date = new \DateTime();
        $date->modify('+1 day');
        $this->setValid($date);

        $this->isValid()->shouldBe(true);
    }

    public function it_is_not_valid()
    {
        $date = new \DateTime();
        $date->modify('-1 day');
        $this->setValid($date);

        $this->isValid()->shouldBe(false);
    }
}
