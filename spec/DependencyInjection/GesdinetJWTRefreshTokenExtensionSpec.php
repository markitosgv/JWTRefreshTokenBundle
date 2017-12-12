<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GesdinetJWTRefreshTokenExtensionSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension');
    }


    public function it_should_set_parameters_correctly(ContainerBuilder $container)
    {
        $container->fileExists(dirname(dirname(__DIR__)).'/DependencyInjection/../Resources/config/services.yml')
                  ->willReturn(true);

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
        $container->setDefinition(Argument::cetera())
                  ->willReturn();
        $container->setAlias(Argument::cetera())
                  ->willReturn();

        $configs = [];
        $this->load($configs, $container);
    }
}
