<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GesdinetJWTRefreshTokenExtensionSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension');
    }

    public function it_should_set_parameters_correctly(ContainerBuilder $container)
    {
        $configs = array();
        $this->load($configs, $container);
    }
}
