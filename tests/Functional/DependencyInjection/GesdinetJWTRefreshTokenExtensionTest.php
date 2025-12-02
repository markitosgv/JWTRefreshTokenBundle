<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Doctrine\DBAL\Connection;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateDBALConnectionCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as RefreshTokenDocument;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as RefreshTokenEntity;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class GesdinetJWTRefreshTokenExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new GesdinetJWTRefreshTokenExtension(),
        ];
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
        $this->expectExceptionMessageMatches('/The DBAL connection service "nonexistent_connection" does not exist.*Please ensure you have.*Installed doctrine\/dbal and doctrine\/doctrine-bundle.*Configured Doctrine DBAL in your config\/packages\/doctrine\.yaml.*Used a valid connection name \(e\.g\., "doctrine\.dbal\.default_connection"\)/s');

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
        $this->assertContainerBuilderHasParameter('gesdinet_jwt_refresh_token.dbal.auto_create_table', true);
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
