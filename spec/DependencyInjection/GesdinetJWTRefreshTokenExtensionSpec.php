<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class GesdinetJWTRefreshTokenExtensionSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension');
    }

    public function it_should_set_parameters_correctly(ContainerBuilder $container, ParameterBag $parameterBag)
    {
        $parameterBag->resolveValue(Argument::any())->will(function ($args) {
            return $args[0];
        });
        $parameterBag->unescapeValue(Argument::any())->will(function ($args) {
            return $args[0];
        });

        $container->getParameterBag()->willReturn($parameterBag);
        $container->fileExists(Argument::any())->willReturn(true);
        $container->setParameter(Argument::any(), Argument::any())->will(function () {
        });
        $container->setDefinition(Argument::any(), Argument::any())->will(function () {
        });
        $container->setAlias(Argument::any(), Argument::any())->will(function () {
        });
        $container->getReflectionClass(Argument::type('string'))->will(function ($args) {
            return new \ReflectionClass($args[0]);
        });
        $container->addResource(Argument::any())->willReturn(null);
        $container->removeBindings(Argument::any())->willReturn(null);

        $container->removeBindings(Argument::any())->will(function () {
        });

        $configs = [];
        $this->load($configs, $container);
    }
}
