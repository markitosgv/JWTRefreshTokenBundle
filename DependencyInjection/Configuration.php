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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gesdinet_jwt_refresh_token');

        $rootNode
            ->children()
                ->integerNode('ttl')->defaultValue('2592000')->end()
                ->booleanNode('ttl_update')->defaultFalse()->end()
                ->scalarNode('firewall')->defaultValue('api')->end()
                ->scalarNode('user_provider')->defaultNull()->end()
                ->scalarNode('refresh_token_entity')
                    ->defaultNull()
                    ->info('Set another refresh token entity to use instead of default one (Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken)')
                ->end()
                ->scalarNode('entity_manager')
                    ->defaultValue('doctrine.orm.entity_manager')
                    ->info('Set entity manager to use (default: doctrine.orm.entity_manager)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
