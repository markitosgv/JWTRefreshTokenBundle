<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Configuration;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The collaborators are built once in setUp(), so a test only sets expectations on the ones it
 * is about. The others stay mock objects without any.
 */
#[AllowMockObjectsWithoutExpectations]
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

    public function test_configuration_is_invalid_when_refresh_token_class_does_not_exist(): void
    {
        // class_implements() returns false for a class that cannot be loaded, which used to reach
        // in_array() and raise a TypeError instead of reporting the configuration as invalid
        $this->assertConfigurationIsInvalid([
            [
                'refresh_token_class' => 'Gesdinet\JWTRefreshTokenBundle\ThisClassDoesNotExist',
            ],
        ]);
    }

    public function test_configuration_is_invalid_when_refresh_token_class_is_not_a_string(): void
    {
        $this->assertConfigurationIsInvalid([
            [
                'refresh_token_class' => 123,
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
}
