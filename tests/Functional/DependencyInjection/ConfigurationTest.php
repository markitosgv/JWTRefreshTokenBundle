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
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
            ],
        ]);
    }

    public function test_custom_configuration_is_valid(): void
    {
        $this->assertConfigurationIsValid([
            [
                'ttl' => 123,
                'ttl_update' => true,
                'manager_type' => 'mongodb',
                'refresh_token_class' => RefreshToken::class,
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
            ],
        ]);
    }

    public function test_configuration_is_invalid_when_refresh_token_class_does_not_implement_the_required_interface(): void
    {
        $this->assertConfigurationIsInvalid([
            [
                'refresh_token_class' => Configuration::class,
            ],
        ]);
    }

    public function test_configuration_is_invalid_when_batch_size_is_negative(): void
    {
        $this->assertConfigurationIsInvalid([
            [
                'refresh_token_class' => RefreshToken::class,
                'default_invalid_batch_size' => -42,
            ],
        ]);
    }

    public function test_dbal_connection_configuration_is_valid(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.custom_connection',
            ],
        ]);
    }

    public function test_dbal_configuration_with_custom_table_name_is_valid(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.default_connection',
                'dbal_table_name' => 'my_refresh_tokens',
            ],
        ]);
    }

    public function test_dbal_configuration_with_custom_columns_is_valid(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.default_connection',
                'dbal_columns' => [
                    'id' => ['name' => 'token_id', 'type' => 'integer'],
                    'refreshToken' => ['name' => 'token_value', 'type' => 'string'],
                    'username' => ['name' => 'user_id', 'type' => 'string'],
                    'valid' => ['name' => 'expires_at', 'type' => 'datetime'],
                ],
            ],
        ]);
    }

    public function test_configuration_is_invalid_when_both_object_manager_and_dbal_connection_are_set(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'object_manager' => 'doctrine.orm.entity_manager',
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                ],
            ],
            'mutually exclusive'
        );
    }

    public function test_dbal_columns_configuration_defaults_to_empty_array(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [],
            ],
            ['dbal_columns' => []],
            'dbal_columns'
        );
    }

    public function test_dbal_table_name_defaults_to_refresh_tokens(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [],
            ],
            ['dbal_table_name' => 'refresh_tokens'],
            'dbal_table_name'
        );
    }

    public function test_dbal_auto_create_table_defaults_to_true(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [],
            ],
            ['dbal_auto_create_table' => true],
            'dbal_auto_create_table'
        );
    }

    public function test_dbal_auto_create_table_can_be_disabled(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.default_connection',
                'dbal_auto_create_table' => false,
            ],
        ]);
    }
}
