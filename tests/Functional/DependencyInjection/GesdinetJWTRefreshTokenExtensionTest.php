<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Doctrine\DBAL\Connection;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateDBALConnectionCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as RefreshTokenDocument;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as RefreshTokenEntity;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

final class GesdinetJWTRefreshTokenExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new GesdinetJWTRefreshTokenExtension(),
        ];
    }

    /**
     * When the node is left out, the bundle looks for an installed mapping. Neither doctrine-bundle
     * nor mongodb-odm-bundle are installed here, which is the situation the message describes.
     */
    public function test_object_manager_must_be_configured_when_no_mapping_is_detected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "object_manager" node must be configured when neither "doctrine/orm" or "doctrine/mongodb-odm" are installed.');

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
        ]);
    }

    /**
     * Storage the bundle knows nothing about, a PDO repository for instance, only has to be named:
     * no object manager is looked for, so none of Doctrine has to be installed at all.
     */
    public function test_a_manager_of_its_own_replaces_everything_doctrine(): void
    {
        $this->container->register('app.pdo_refresh_token_manager', RefreshTokenManagerInterface::class);

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'refresh_token_manager' => 'app.pdo_refresh_token_manager',
        ]);

        $this->assertContainerBuilderHasAlias('gesdinet_jwt_refresh_token.refresh_token_manager', 'app.pdo_refresh_token_manager');
        $this->assertContainerBuilderHasAlias(RefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');

        $this->assertFalse(
            $this->container->has('gesdinet_jwt_refresh_token.object_manager'),
            'No object manager should be looked for when the manager is supplied'
        );
        $this->assertFalse(
            $this->container->has('gesdinet_jwt_refresh_token.dbal.connection'),
            'No connection should be looked for either'
        );
    }

    /**
     * The listeners and commands are wired to the service id, so aliasing it has to be enough for
     * them to reach a manager the bundle did not build.
     */
    public function test_the_supplied_manager_is_what_the_rest_of_the_bundle_is_given(): void
    {
        $this->container->register('app.pdo_refresh_token_manager', RefreshTokenManagerInterface::class);

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'refresh_token_manager' => 'app.pdo_refresh_token_manager',
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'gesdinet_jwt_refresh_token.event_listener.attach_refresh_token',
            0,
            new Reference('gesdinet_jwt_refresh_token.refresh_token_manager')
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function listenerOptionProvider(): iterable
    {
        yield 'ttl' => [1, 'ttl'];
        yield 'token_parameter_name' => [3, 'token_parameter_name'];
        // The one this is really here for: single_use was configurable for a long time while the
        // value never reached the listener, so it did nothing and the configuration looked ignored
        yield 'single_use' => [4, 'single_use'];
        yield 'cookie' => [7, 'cookie'];
        yield 'return_expiration' => [8, 'return_expiration'];
        yield 'return_expiration_parameter_name' => [9, 'return_expiration_parameter_name'];
        yield 'single_use_ttl_update' => [10, 'single_use_ttl_update'];
        yield 'max_tokens_per_user' => [11, 'max_tokens_per_user'];
    }

    /**
     * A configured option that never reaches the listener is worse than one that does not exist:
     * it is set, it is accepted, and nothing happens.
     */
    #[DataProvider('listenerOptionProvider')]
    public function test_every_configured_option_reaches_the_listener(int $argument, string $option): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'gesdinet_jwt_refresh_token.event_listener.attach_refresh_token',
            $argument,
            sprintf('%%gesdinet_jwt_refresh_token.%s%%', $option)
        );
    }

    public function test_blocking_the_previous_jwt_is_not_wired_unless_it_is_asked_for(): void
    {
        $this->load(['refresh_token_class' => RefreshTokenEntity::class, 'object_manager' => 'doctrine.orm.entity_manager']);

        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.event_listener.block_previous_jwt'));
    }

    public function test_blocking_the_previous_jwt_listens_for_a_refresh(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'block_previous_jwt' => true,
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'gesdinet_jwt_refresh_token.event_listener.block_previous_jwt',
            'kernel.event_listener',
            ['event' => 'gesdinet.refresh_token']
        );
    }

    public function test_tokens_are_not_hashed_unless_it_is_asked_for(): void
    {
        $this->load(['refresh_token_class' => RefreshTokenEntity::class, 'object_manager' => 'doctrine.orm.entity_manager']);

        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.hashed_refresh_token_manager'));
    }

    /**
     * It decorates rather than replaces, so it wraps whichever backend is in use and every alias
     * still resolves to it.
     */
    public function test_hashing_wraps_the_manager_of_whichever_backend_is_in_use(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'hash_tokens' => ['enabled' => true],
        ]);

        $decoration = $this->container->getDefinition('gesdinet_jwt_refresh_token.hashed_refresh_token_manager')->getDecoratedService();

        $this->assertNotNull($decoration);
        $this->assertSame('gesdinet_jwt_refresh_token.refresh_token_manager', $decoration[0]);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.hash_tokens.accept_stored_in_the_clear', true);
    }

    public function test_the_endpoint_is_not_rate_limited_unless_it_is_asked_for(): void
    {
        $this->load(['refresh_token_class' => RefreshTokenEntity::class, 'object_manager' => 'doctrine.orm.entity_manager']);

        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.refresh_rate_limiter'));
    }

    public function test_rate_limiting_consumes_from_the_configured_limiter(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'rate_limiter' => ['enabled' => true, 'limiter' => 'limiter.refresh', 'key' => 'token'],
        ]);

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.rate_limiter.limiter', 'limiter.refresh');
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.rate_limiter.key', 'token');
        $this->assertContainerBuilderHasService('gesdinet_jwt_refresh_token.refresh_rate_limiter');
    }

    public function test_reuse_is_not_detected_unless_it_is_asked_for(): void
    {
        $this->load(['refresh_token_class' => RefreshTokenEntity::class, 'object_manager' => 'doctrine.orm.entity_manager']);

        // The authenticator and the listener ask for these with nullOnInvalid(), so leaving them
        // undefined is what turns the feature off
        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.spent_refresh_token_registry'));
        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.refresh_token_reuse_detector'));
    }

    public function test_reuse_detection_registers_the_registry_over_the_configured_pool(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'single_use' => true,
            'reuse_detection' => ['enabled' => true, 'cache' => 'cache.redis', 'ttl' => 900],
        ]);

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.reuse_detection.cache', 'cache.redis');
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.reuse_detection.ttl', 900);
        $this->assertContainerBuilderHasService('gesdinet_jwt_refresh_token.spent_refresh_token_registry');
        $this->assertContainerBuilderHasService('gesdinet_jwt_refresh_token.refresh_token_reuse_detector');
    }

    /**
     * Without single_use a token is not replaced when it is used, so nothing is ever spent and
     * nothing can be recognised. Left to load, it would read in configuration as protection while
     * detecting nothing at all.
     */
    public function test_refuses_reuse_detection_without_single_use(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('needs "single_use" to be true');

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'reuse_detection' => ['enabled' => true],
        ]);
    }

    public function test_the_openapi_factory_is_not_registered_unless_it_is_asked_for(): void
    {
        $this->load(['refresh_token_class' => RefreshTokenEntity::class, 'object_manager' => 'doctrine.orm.entity_manager']);

        $this->assertFalse(
            $this->container->hasDefinition('gesdinet_jwt_refresh_token.api_platform.openapi.factory'),
            'An application documenting the endpoint by hand would otherwise end up with it twice'
        );
    }

    /**
     * Lexik decorates the same factory at priority 0, so this one has to be handed the
     * specification it has already contributed the login endpoint to.
     */
    public function test_the_openapi_factory_decorates_api_platform_after_lexik(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'api_platform' => ['enabled' => true],
        ]);

        $definition = $this->container->getDefinition('gesdinet_jwt_refresh_token.api_platform.openapi.factory');

        $decoration = $definition->getDecoratedService();

        $this->assertNotNull($decoration);
        $this->assertSame('api_platform.openapi.factory', $decoration[0]);
        $this->assertLessThan(0, $decoration[2], 'A lower priority than Lexik is what puts this one after it');
    }

    /**
     * The paths come from the firewall, which may not have the authenticator on it at all.
     */
    public function test_the_openapi_factory_is_given_an_empty_path_list_rather_than_a_missing_one(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'api_platform' => ['enabled' => true],
        ]);

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.check_paths', []);
    }

    public function test_container_is_loaded_with_default_configuration(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
        ]);

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.ttl', 2592000);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.ttl_update', false);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.single_use', false);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.token_parameter_name', 'refresh_token');
        $this->assertContainerBuilderHasParameter(
            'gesdinet_jwt_refresh_token.cookie',
            [
                'enabled' => false,
                'same_site' => 'lax',
                'path' => '/',
                'domain' => null,
                'secure' => true,
                'http_only' => true,
                'partitioned' => false,
                'remove_token_from_body' => true,
            ],
        );

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.refresh_token.class', RefreshTokenEntity::class);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.default_invalid_batch_size', RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);
        $this->assertContainerBuilderHasAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine.orm.entity_manager');
    }

    public function test_the_manager_is_injectable_through_both_of_its_interfaces(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'object_manager' => 'doctrine.orm.entity_manager',
        ]);

        $this->assertContainerBuilderHasAlias(RefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');
        $this->assertContainerBuilderHasAlias(RevokeRefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');
    }

    /**
     * The DBAL manager does not revoke by user, so nothing should offer it under that interface.
     */
    /**
     * The DBAL manager did not revoke by user when it was written, so the alias was left out for it.
     * It does now, and leaving it out would mean the option resting on it could not be used there.
     */
    public function test_the_dbal_manager_is_offered_through_the_same_interfaces(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'dbal_connection' => 'doctrine.dbal.default_connection',
        ]);

        $this->assertContainerBuilderHasAlias(RefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');
        $this->assertContainerBuilderHasAlias(RevokeRefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');
        $this->assertContainerBuilderHasAlias(ListRefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');
    }

    public function test_container_is_loaded_with_custom_configuration(): void
    {
        $this->load([
            'ttl' => 123,
            'ttl_update' => true,
            'manager_type' => 'mongodb',
            'refresh_token_class' => RefreshTokenDocument::class,
            'object_manager' => 'doctrine_mongodb.odm.document_manager',
            'single_use' => true,
            'token_parameter_name' => 'the_token',
            'cookie' => [
                'enabled' => true,
                'same_site' => 'strict',
                'path' => '/api/',
                'domain' => 'example.com',
                'secure' => false,
                'http_only' => false,
                'partitioned' => true,
            ],
            'default_invalid_batch_size' => 42,
        ]);

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.ttl', 123);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.ttl_update', true);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.single_use', true);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.token_parameter_name', 'the_token');
        $this->assertContainerBuilderHasParameter(
            'gesdinet_jwt_refresh_token.cookie',
            [
                'enabled' => true,
                'same_site' => 'strict',
                'path' => '/api/',
                'domain' => 'example.com',
                'secure' => false,
                'http_only' => false,
                'partitioned' => true,
                'remove_token_from_body' => true,
            ],
        );

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.refresh_token.class', RefreshTokenDocument::class);
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.default_invalid_batch_size', 42);
        $this->assertContainerBuilderHasAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine_mongodb.odm.document_manager');
    }

    public function test_throws_exception_when_dbal_connection_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/The\s+DBAL\s+connection\s+service\s+"nonexistent_connection"\s+does\s+not\s+exist.*Please\s+ensure\s+you\s+have.*Installed\s+doctrine\/dbal\s+and\s+doctrine\/doctrine-bundle.*Configured\s+Doctrine\s+DBAL\s+in\s+your\s+config\/packages\/doctrine\.yaml.*Used\s+a\s+valid\s+connection\s+name\s+\(e\.g\.,\s+"doctrine\.dbal\.default_connection"\)/s');

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'dbal_connection' => 'nonexistent_connection',
        ]);

        $this->container->addCompilerPass(new ValidateDBALConnectionCompilerPass());
        $this->compile();
    }

    public function test_container_is_loaded_with_valid_dbal_connection(): void
    {
        // Register a mock DBAL connection service
        $this->container->register('doctrine.dbal.default_connection', Connection::class);

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'dbal_connection' => 'doctrine.dbal.default_connection',
        ]);

        $this->container->addCompilerPass(new ValidateDBALConnectionCompilerPass());
        $this->compile();

        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.dbal.connection', 'doctrine.dbal.default_connection');
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.dbal.table_name', 'refresh_tokens');
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.dbal.auto_create_table', false);
        $this->assertContainerBuilderHasService('gesdinet_jwt_refresh_token.refresh_token_manager');
        $this->assertContainerBuilderHasService('gesdinet_jwt_refresh_token.dbal.table_schema_manager');
    }

    public function test_lists_available_dbal_connections_in_error_message(): void
    {
        // Register some DBAL connections
        $this->container->register('doctrine.dbal.default_connection', Connection::class);
        $this->container->register('doctrine.dbal.custom_connection', Connection::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Available DBAL connections: doctrine.dbal.default_connection, doctrine.dbal.custom_connection');

        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'dbal_connection' => 'invalid_connection',
        ]);

        $this->container->addCompilerPass(new ValidateDBALConnectionCompilerPass());
        $this->compile();
    }
}
