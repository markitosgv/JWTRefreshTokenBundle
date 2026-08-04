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
    /**
     * Validates that a string is a valid SQL identifier.
     *
     * Valid SQL identifiers must start with a letter or underscore and contain
     * only letters, numbers, and underscores. This prevents potential SQL injection
     * risks and ensures compatibility across database platforms.
     *
     * Written without a regular expression so that it holds no global state, which is what lets
     * both analysers treat it as pure.
     *
     * @psalm-pure
     */
    private static function isValidSqlIdentifier(string $identifier): bool
    {
        if ('' === $identifier) {
            return false;
        }

        if ('_' !== $identifier[0] && !ctype_alpha($identifier[0])) {
            return false;
        }

        for ($i = 1, $length = strlen($identifier); $i < $length; ++$i) {
            if ('_' !== $identifier[$i] && !ctype_alnum($identifier[$i])) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gesdinet_jwt_refresh_token');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->integerNode('ttl')
                    ->defaultValue(2592000)
                    // min() rather than a validate() closure: it is skipped while the placeholder
                    // of an environment variable is being checked, and a closure is not, so a
                    // closure rejects every `%env(int:...)%` on the sample value of 0
                    ->min(1)
                    ->info('How long a refresh token lasts, in seconds, for all authenticators. There is no value meaning never: a token is valid until this many seconds after it was issued, so a long lived one is a large number, 315360000 being ten years.')
                ->end()
                ->integerNode('max_tokens_per_user')
                    ->defaultNull()
                    ->min(1)
                    ->info('How many refresh tokens a user may hold at once. Each login stores one, so this is a limit on signed-in devices: signing in beyond it revokes the session that has gone longest without being refreshed. Unlimited when not set.')
                ->end()
                ->booleanNode('ttl_update')
                    ->defaultFalse()
                    ->info('The default update TTL flag for all authenticators.')
                ->end()
                ->booleanNode('single_use_ttl_update')
                    ->defaultTrue()
                    ->info('Whether a token issued in place of a single use one starts its ttl over. Turn it off to have it expire when the one it replaced would have, so that refreshing cannot be chained indefinitely.')
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
                        ->ifTrue(static function (mixed $v): bool {
                            // Checked before class_implements(), which raises a warning of its own
                            // when the class cannot be loaded
                            if (!\is_string($v) || !class_exists($v)) {
                                return true;
                            }

                            $interfaces = class_implements($v);

                            return false === $interfaces || !\in_array(RefreshTokenInterface::class, $interfaces, true);
                        })
                        ->thenInvalid(sprintf('The "refresh_token_class" class must implement "%s".', RefreshTokenInterface::class))
                    ->end()
                ->end()
                ->arrayNode('hash_tokens')
                    ->canBeEnabled()
                    ->info('Stores the hash of a refresh token instead of the token, so a copy of the database cannot be used to refresh. Off by default.')
                    ->children()
                        ->booleanNode('accept_stored_in_the_clear')
                            ->defaultTrue()
                            ->info('Whether a token stored before this was turned on is still accepted, and rewritten hashed the first time it is used. Turn it off once they have all expired, so that a token read from an old backup cannot be used.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('api_platform')
                    ->canBeEnabled()
                    ->info('Documents the refresh token in the OpenAPI specification API Platform generates. Off by default, since an application that already documents it by hand would end up with it twice.')
                ->end()
                ->scalarNode('refresh_token_manager')
                    ->defaultNull()
                    ->info('Set your own service implementing RefreshTokenManagerInterface, storing the tokens however you like. Nothing Doctrine is wired when this is set, so the bundle works without it. Mutually exclusive with object_manager and dbal_connection.')
                ->end()
                ->scalarNode('object_manager')
                    ->defaultNull()
                    ->info('The object manager service to store the tokens through, as a service id rather than the name an entity manager is configured under: an entity manager named "foo" is the service "doctrine.orm.foo_entity_manager". Defaults to doctrine.orm.entity_manager. Mutually exclusive with dbal_connection.')
                ->end()
                ->scalarNode('dbal_connection')
                    ->defaultNull()
                    ->info('Set the DBAL connection to use for direct database access. Mutually exclusive with object_manager.')
                ->end()
                ->scalarNode('dbal_table_name')
                    ->defaultValue('refresh_tokens')
                    ->info('The table name for refresh tokens when using DBAL')
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => !is_string($v) || !self::isValidSqlIdentifier($v))
                        ->thenInvalid('The "dbal_table_name" must be a valid SQL identifier (alphanumeric and underscores only, starting with letter or underscore). Got: %s')
                    ->end()
                ->end()
                ->booleanNode('dbal_auto_create_table')
                    ->defaultFalse()
                    ->info('Create the refresh tokens table on the first request when it does not exist. Off by default: it runs DDL while serving traffic, so the connection needs rights to alter the schema. Prefer a migration.')
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
                                ->validate()
                                    ->ifTrue(static fn (mixed $v): bool => !is_string($v) || !self::isValidSqlIdentifier($v))
                                    ->thenInvalid('The column name must be a valid SQL identifier (alphanumeric and underscores only, starting with letter or underscore). Got: %s')
                                ->end()
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
                        ->scalarNode('same_site')
                            ->defaultValue('lax')
                            ->info('One of "none", "lax" or "strict", or empty to leave the attribute off the cookie, which is what Symfony\'s Cookie takes an empty value to mean. Matched without regard to case, as Cookie does.')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => null !== $v
                                    && (!is_string($v) || !in_array(strtolower($v), ['', 'none', 'lax', 'strict'], true)))
                                ->thenInvalid('The "same_site" cookie option must be one of "none", "lax" or "strict", or empty to leave the attribute off. Got: %s')
                            ->end()
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
                    ->min(1)
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => null !== ($v['object_manager'] ?? null) && null !== ($v['dbal_connection'] ?? null))
                ->thenInvalid('The "object_manager" and "dbal_connection" options are mutually exclusive. Use one or the other.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => null !== ($v['refresh_token_manager'] ?? null)
                    && (null !== ($v['object_manager'] ?? null) || null !== ($v['dbal_connection'] ?? null)))
                ->thenInvalid('The "refresh_token_manager" option replaces the manager the bundle would build, so "object_manager" and "dbal_connection" have nothing left to configure. Drop them, or drop "refresh_token_manager".')
            ->end();

        return $treeBuilder;
    }
}
