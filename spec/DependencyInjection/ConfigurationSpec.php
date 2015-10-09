<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use PhpSpec\ObjectBehavior;

class ConfigurationSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Configuration');
    }

    public function it_should_set_the_tree_builder_config()
    {
        $this
            ->getConfigTreeBuilder()
            ->shouldReturnAnInstanceOf('Symfony\Component\Config\Definition\Builder\TreeBuilder')
        ;
    }
}
