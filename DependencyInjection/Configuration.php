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

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Symfony\Component\Config\Definition\BaseNode;
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
                ->integerNode('ttl')
                    ->defaultValue(2592000)
                    ->info('The default TTL for all authenticators.')
                ->end()
                ->booleanNode('ttl_update')
                    ->defaultFalse()
                    ->info('The default update TTL flag for all authenticators.')
                ->end()
                ->scalarNode('firewall')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated without replacement.', '1.0'))
                    ->defaultValue('api')
                ->end()
                ->scalarNode('user_provider')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated without replacement.', '1.0'))
                    ->defaultNull()
                ->end()
                ->scalarNode('user_identity_field')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated without replacement.', '1.0'))
                    ->defaultValue('username')
                ->end()
                ->scalarNode('manager_type')
                    ->defaultValue('orm')
                    ->info('Set the type of object manager to use (default: orm)')
                ->end()
                ->scalarNode('refresh_token_class')
                    ->defaultNull()
                    ->info(sprintf('Set the refresh token class to use (default: %s)', RefreshToken::class))
                ->end()
                ->scalarNode('object_manager')
                    ->defaultNull()
                    ->info('Set the object manager to use (default: doctrine.orm.entity_manager)')
                ->end()
                ->scalarNode('user_checker')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated without replacement.', '1.0'))
                    ->defaultValue('security.user_checker')
                ->end()
                ->scalarNode('refresh_token_entity')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated, use the "refresh_token_class" node instead.', '0.5'))
                    ->defaultNull()
                    ->info(sprintf('Set the refresh token class to use (default: %s)', RefreshToken::class))
                ->end()
                ->scalarNode('entity_manager')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated, use the "object_manager" node instead.', '0.5'))
                    ->defaultNull()
                    ->info('Set the entity manager to use')
                ->end()
                ->scalarNode('single_use')
                    ->defaultFalse()
                    ->info('When true, generate a new refresh token on consumption (deleting the old one)')
                ->end()
                ->scalarNode('token_parameter_name')
                    ->defaultValue('refresh_token')
                    ->info('The default request parameter name containing the refresh token for all authenticators.')
                ->end()
                ->booleanNode('doctrine_mappings')
                    ->setDeprecated(...$this->getDeprecationParameters('The "%node%" node is deprecated without replacement.', '1.0'))
                    ->info('When true, resolving of Doctrine mapping is done automatically to use either ORM or ODM object manager')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function getDeprecationParameters(string $message, string $version): array
    {
        if (method_exists(BaseNode::class, 'getDeprecation')) {
            return ['gesdinet/jwt-refresh-token-bundle', $version, $message];
        }

        return [$message];
    }
}
