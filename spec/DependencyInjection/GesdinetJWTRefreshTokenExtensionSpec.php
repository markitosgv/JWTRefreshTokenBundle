<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class GesdinetJWTRefreshTokenExtensionSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(GesdinetJWTRefreshTokenExtension::class);
    }

    public function it_should_set_parameters_correctly(ContainerBuilder $container)
    {
        $configs = [];
        $container->setParameter(Argument::type('string'), Argument::any())->shouldBeCalled();
        $container->setDefinition(Argument::type('string'), Argument::type(Definition::class))->shouldBeCalled();
        $container->fileExists(Argument::type('string'))->willReturn(true);
        $this->load($configs, $container);
    }
}
