<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Gesdinet\JWTRefreshTokenBundle\Events;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class GesdinetJWTRefreshTokenExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $config['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $config['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.security.firewall', $config['firewall']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_provider', $config['user_provider']);

        //if refresh_token_entity has not be defined in config, we don't want to erase base value
        if (isset($config['refresh_token_entity'])) {
            $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', $config['refresh_token_entity']);
        }

        $container->setParameter('gesdinet.jwtrefreshtoken.entity_manager.id', $config['entity_manager']);

        if($config['methods']['request_body']['enabled']){
            $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenExtractor\RequestBodyTokenExtractorEventListener::class, [$config['methods']['request_body']['name']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::GET_TOKEN_REQUEST, 'method' => 'onGetToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.extractor.request_body', $definition);

            $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenSetter\ResponseBodyTokenSetterEventListener::class, [$config['methods']['request_body']['name']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::ADD_TOKEN_RESPONSE, 'method' => 'onAddToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.setter.response_body', $definition);
        }

        if($config['methods']['request_header']['enabled']){
            $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenExtractor\RequestHeaderTokenExtractorEventListener::class, [$config['methods']['request_header']['name']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::GET_TOKEN_REQUEST, 'method' => 'onGetToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.extractor.request_header', $definition);

             $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenSetter\ResponseHeaderTokenSetterEventListener::class, [$config['methods']['request_header']['name']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::ADD_TOKEN_RESPONSE, 'method' => 'onAddToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.setter.response_header', $definition);
        }

        if($config['methods']['cookie']['enabled']){
            $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenExtractor\RequestCookieTokenExtractorEventListener::class, [$config['methods']['cookie']['name']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::GET_TOKEN_REQUEST, 'method' => 'onGetToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.extractor.cookie', $definition);

            $definition = new Definition(\Gesdinet\JWTRefreshTokenBundle\EventListener\TokenSetter\ResponseCookieTokenSetterEventListener::class, [$config['methods']['cookie']['name'], $config['ttl']]);
            $definition->addTag('kernel.event_listener', ['event' => Events::ADD_TOKEN_RESPONSE, 'method' => 'onAddToken']);
            $container->setDefinition('gesdinet_jwt_refresh_token.setter.cookie', $definition);
        }
    }
}
