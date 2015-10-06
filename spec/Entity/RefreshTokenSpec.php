<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use PhpSpec\ObjectBehavior;

class RefreshTokenSpec extends ObjectBehavior
{
    public function let()
    {
        $this->setUsername('test');
        $this->setRefreshToken();
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken');
    }

    public function it_has_a_custom_refresh_token()
    {
        $this->setRefreshToken('token');
        $this->getRefreshToken()->shouldBe('token');
    }

    public function it_has_username()
    {
        $this->getUsername()->shouldBe('test');
    }

    public function it_has_valid()
    {
        $date = new \DateTime();
        $this->setValid($date);

        $this->getValid()->shouldBe($date);
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
