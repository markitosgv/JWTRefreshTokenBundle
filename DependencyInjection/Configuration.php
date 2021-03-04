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
        $treeBuilder = new TreeBuilder('gesdinet_jwt_refresh_token');
        $rootNode = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('gesdinet_jwt_refresh_token');

        $rootNode
            ->children()
                ->integerNode('ttl')->defaultValue(2592000)->end()
                ->booleanNode('ttl_update')->defaultFalse()->end()
                ->scalarNode('firewall')->defaultValue('api')->end()
                ->scalarNode('user_provider')->defaultNull()->end()
                ->scalarNode('user_identity_field')->defaultValue('username')->end()
                ->scalarNode('manager_type')
                    ->defaultValue('orm')
                    ->info('Set manager mode instead of default one (orm)')
                    ->end()
                ->scalarNode('refresh_token_class')
                    ->defaultNull()
                    ->info('Set another refresh token class to use instead of default one (Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken)')
                    ->end()
                ->scalarNode('object_manager')
                    ->defaultNull()
                    ->info('Set object manager to use (default: doctrine.orm.entity_manager)')
                    ->end()
                ->scalarNode('user_checker')->defaultValue('security.user_checker')->end()
                ->scalarNode('refresh_token_entity')
                    ->defaultNull()
                    ->info('Deprecated, use refresh_token_class instead')
                    ->end()
                ->scalarNode('entity_manager')
                    ->defaultNull()
                    ->info('Deprecated, use object_manager instead')
                    ->end()
                ->scalarNode('single_use')
                    ->defaultFalse()
                    ->info('When true, generate a new refresh token on consumption (deleting the old one)')
                    ->end()
                ->scalarNode('token_parameter_name')->defaultValue('refresh_token')->end()
                ->booleanNode('doctrine_mappings')
                    ->info('When true, resolving of Doctrine mapping is done automatically to use either ORM or ODM object manager')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
