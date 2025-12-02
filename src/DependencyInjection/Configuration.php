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

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gesdinet_jwt_refresh_token');
        $rootNode = $treeBuilder->getRootNode();

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
                ->scalarNode('manager_type')
                    ->defaultValue('orm')
                    ->info('Set the type of object manager to use (default: orm)')
                ->end()
                ->scalarNode('refresh_token_class')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Set the refresh token class to use')
                    ->validate()
                        ->ifTrue(static fn ($v): bool => null === $v || !\in_array(RefreshTokenInterface::class, class_implements($v), true))
                        ->thenInvalid(sprintf('The "refresh_token_class" class must implement "%s".', RefreshTokenInterface::class))
                    ->end()
                ->end()
                ->scalarNode('object_manager')
                    ->defaultNull()
                    ->info('Set the object manager to use (default: doctrine.orm.entity_manager). Mutually exclusive with dbal_connection.')
                ->end()
                ->scalarNode('dbal_connection')
                    ->defaultNull()
                    ->info('Set the DBAL connection to use for direct database access. Mutually exclusive with object_manager.')
                ->end()
                ->scalarNode('dbal_table_name')
                    ->defaultValue('refresh_tokens')
                    ->info('The table name for refresh tokens when using DBAL')
                ->end()
                ->booleanNode('dbal_auto_create_table')
                    ->defaultTrue()
                    ->info('Automatically create the refresh tokens table if it does not exist (like Symfony Messenger)')
                ->end()
                ->arrayNode('dbal_columns')
                    ->defaultValue([])
                    ->useAttributeAsKey('alias')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('The actual column name in the database')
                            ->end()
                            ->scalarNode('type')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('The DBAL type (integer, string, datetime, etc.)')
                            ->end()
                        ->end()
                    ->end()
                    ->info('Custom column mapping for DBAL persistence layer')
                ->end()
                ->scalarNode('single_use')
                    ->defaultFalse()
                    ->info('When true, generate a new refresh token on consumption (deleting the old one)')
                ->end()
                ->scalarNode('token_parameter_name')
                    ->defaultValue('refresh_token')
                    ->info('The default request parameter name containing the refresh token for all authenticators.')
                ->end()
                ->arrayNode('cookie')
                    ->canBeEnabled()
                    ->children()
                        ->enumNode('same_site')
                            ->values(['none', 'lax', 'strict'])
                            ->defaultValue('lax')
                        ->end()
                        ->scalarNode('path')->defaultValue('/')->cannotBeEmpty()->end()
                        ->scalarNode('domain')->defaultNull()->end()
                        ->scalarNode('http_only')->defaultTrue()->end()
                        ->scalarNode('secure')->defaultTrue()->end()
                        ->scalarNode('partitioned')->defaultFalse()->end()
                        ->scalarNode('remove_token_from_body')->defaultTrue()->end()
                    ->end()
                ->end()
                ->scalarNode('return_expiration')
                    ->defaultFalse()
                    ->info('When true, the response will include the token expiration timestamp')
                ->end()
                ->scalarNode('return_expiration_parameter_name')
                    ->defaultValue('refresh_token_expiration')
                    ->info('The default response parameter name containing the refresh token expiration timestamp')
                ->end()
                ->integerNode('default_invalid_batch_size')
                    ->defaultValue(RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE)
                    ->info('The default batch size when clearing invalid tokens')
                    ->min(0)
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => null !== $v['object_manager'] && null !== $v['dbal_connection'])
                ->thenInvalid('The "object_manager" and "dbal_connection" options are mutually exclusive. Use one or the other.')
            ->end();

        return $treeBuilder;
    }
}
