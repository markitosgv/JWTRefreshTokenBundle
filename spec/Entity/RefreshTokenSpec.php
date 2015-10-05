<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefreshTokenSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken');
    }

    function it_has_a_refresh_token()
    {
        $this->setRefreshToken();
        $this->getRefreshToken()->shouldBeString();
    }

    function it_has_a_custom_refresh_token()
    {
        $this->setRefreshToken('token');
        $this->getRefreshToken()->shouldBe('token');
    }

    function it_has_username()
    {
        $this->setUsername("test");
        $this->getUsername()->shouldBe("test");
    }

}
