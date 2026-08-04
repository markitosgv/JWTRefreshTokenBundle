<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Doctrine\DBAL\Connection;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateDBALConnectionCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as RefreshTokenDocument;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as RefreshTokenEntity;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
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
    public function test_the_dbal_manager_is_not_offered_as_revoking_by_user(): void
    {
        $this->load([
            'refresh_token_class' => RefreshTokenEntity::class,
            'dbal_connection' => 'doctrine.dbal.default_connection',
        ]);

        $this->assertFalse(
            $this->container->hasAlias(RevokeRefreshTokenManagerInterface::class),
            'Injecting it would hand over a manager without the method'
        );
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
