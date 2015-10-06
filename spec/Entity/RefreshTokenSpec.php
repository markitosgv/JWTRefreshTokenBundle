<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefreshTokenSpec extends ObjectBehavior
{
    function let()
    {
        $this->setUsername("test");
        $this->setRefreshToken();
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken');
    }

    function it_has_a_custom_refresh_token()
    {
        $this->setRefreshToken('token');
        $this->getRefreshToken()->shouldBe('token');
    }

    function it_has_username()
    {
        $this->getUsername()->shouldBe("test");
    }

    function it_has_valid()
    {
        $date = new \DateTime();
        $this->setValid($date);

        $this->getValid()->shouldBe($date);
    }

    function it_is_valid()
    {
        $date = new \DateTime();
        $date->modify("+1 day");
        $this->setValid($date);

        $this->isValid()->shouldBe(true);
    }

    function it_is_not_valid()
    {
        $date = new \DateTime();
        $date->modify("-1 day");
        $this->setValid($date);

        $this->isValid()->shouldBe(false);
    }

}