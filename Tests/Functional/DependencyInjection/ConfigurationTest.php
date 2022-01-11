<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Configuration;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration();
    }

    public function test_default_configuration_is_valid(): void
    {
        $this->assertConfigurationIsValid([]);
    }

    public function test_custom_configuration_is_valid(): void
    {
        $this->assertConfigurationIsValid([
            [
                'ttl' => 123,
                'ttl_update' => true,
                'firewall' => 'main',
                'user_provider' => 'my.user_provider',
                'user_identity_field' => 'email',
                'manager_type' => 'mongodb',
                'refresh_token_class' => RefreshToken::class,
                'object_manager' => 'doctrine_mongodb.odm.document_manager',
                'user_checker' => 'my.user_checker',
                'single_use' => true,
                'token_parameter_name' => 'the_token',
                'doctrine_mappings' => false,
                'cookie' => [
                    'enabled' => true,
                    'same_site' => 'strict',
                    'path' => '/api/',
                    'domain' => 'example.com',
                    'secure' => false,
                    'http_only' => false,
                ],
            ],
        ]);
    }
}
