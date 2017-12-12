<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class GesdinetJWTRefreshTokenExtensionSpec extends ObjectBehavior
{
    public function let(ContainerBuilder $container)
    {
        $container->fileExists(dirname(dirname(__DIR__)).'/DependencyInjection/../Resources/config/services.yml')
                  ->willReturn(true);
        $container->has('gesdinet.jwtrefreshtoken.name_generator.underscore')
                  ->willReturn(true);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension');
    }

    public function it_should_set_parameters_correctly(ContainerBuilder $container)
    {
        // setParameter calls with default values
        $container->setParameter('gesdinet_jwt_refresh_token.ttl', 2592000)
                  ->shouldBeCalled();
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', false)
                  ->shouldBeCalled();
        $container->setParameter('gesdinet_jwt_refresh_token.security.firewall', 'api')
                  ->shouldBeCalled();
        $container->setParameter('gesdinet_jwt_refresh_token.user_provider', null)
                  ->shouldBeCalled();
        $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', RefreshToken::class)
                  ->shouldBeCalled();
        $container->setParameter('gesdinet.jwtrefreshtoken.entity_manager.id', 'doctrine.orm.entity_manager')
                  ->shouldBeCalled();

        // Ignore these calls
        $container->has(Argument::cetera())
                  ->willReturn();
        $container->setDefinition(Argument::cetera())
                  ->willReturn();
        $container->setAlias(Argument::cetera())
                  ->willReturn();

        $configs = [];
        $this->load($configs, $container);
    }

    public function it_should_configure_the_default_naming_generator(ContainerBuilder $container)
    {
        $container->setAlias(
            'gesdinet.jwtrefreshtoken.name_generator.default',
            Argument::exact(new Alias('gesdinet.jwtrefreshtoken.name_generator.underscore'))
        )
                  ->shouldBeCalled();

        // Ignore these calls
        $container->setParameter(Argument::cetera())
                  ->willReturn();
        $container->setDefinition(Argument::cetera())
                  ->willReturn();

        $configs = [];
        $this->load($configs, $container);
    }

    public function it_should_throw_an_exception_if_specifying_a_non_existent_name_generator_service(
        ContainerBuilder $container
    ) {
        $container->has('some.service.name')
                  ->willReturn(false);

        // Ignore these calls
        $container->setParameter(Argument::cetera())
                  ->willReturn();
        $container->setDefinition(Argument::cetera())
                  ->willReturn();
        $container->setAlias(Argument::cetera())
                  ->willReturn();

        $configs = [['parameter_name_generator' => 'some.service.name']];
        $this->shouldThrow(ServiceNotFoundException::class)
             ->during('load', [$configs, $container]);
    }

    public function it_should_configure_a_custom_name_generator(ContainerBuilder $container)
    {
        // Expectations
        $container->setAlias('gesdinet.jwtrefreshtoken.name_generator.default', 'some.service.name')
                  ->shouldBeCalled();

        // Stubs
        $container->has('some.service.name')
                  ->willReturn(true);

        // Ignore these calls
        $container->setParameter(Argument::cetera())
                  ->willReturn();
        $container->setDefinition(Argument::cetera())
                  ->willReturn();
        $container->setAlias(Argument::cetera())
                  ->willReturn();

        $configs = [['parameter_name_generator' => 'some.service.name']];
        $this->load($configs, $container);
    }
}
