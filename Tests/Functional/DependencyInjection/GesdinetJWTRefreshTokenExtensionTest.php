<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as RefreshTokenDocument;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as RefreshTokenEntity;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

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
        $this->load();

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

        $this->assertContainerBuilderHasParameter('gesdinet.jwtrefreshtoken.refresh_token.class', RefreshTokenEntity::class);
        $this->assertContainerBuilderHasParameter('gesdinet.jwtrefreshtoken.object_manager.id', 'doctrine.orm.entity_manager');
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

        $this->assertContainerBuilderHasParameter('gesdinet.jwtrefreshtoken.refresh_token.class', RefreshTokenDocument::class);
        $this->assertContainerBuilderHasParameter('gesdinet.jwtrefreshtoken.object_manager.id', 'doctrine_mongodb.odm.document_manager');
    }
}
