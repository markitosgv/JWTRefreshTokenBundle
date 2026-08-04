<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Configuration;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function backendProvider(): iterable
    {
        yield 'an object manager' => ['object_manager', 'doctrine.orm.entity_manager'];
        yield 'a DBAL connection' => ['dbal_connection', 'doctrine.dbal.default_connection'];
    }

    #[DataProvider('backendProvider')]
    public function test_configuration_is_invalid_when_a_manager_of_its_own_is_given_a_backend_too(string $node, string $service): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'refresh_token_manager' => 'app.pdo_refresh_token_manager',
                    $node => $service,
                ],
            ],
            'nothing left to configure'
        );
    }

    public function test_a_manager_of_its_own_is_valid_on_its_own(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'refresh_token_manager' => 'app.pdo_refresh_token_manager',
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameSiteProvider(): iterable
    {
        yield 'none' => ['none'];
        yield 'lax' => ['lax'];
        yield 'strict' => ['strict'];
        // Cookie lowercases it, and an environment variable is as likely to be written this way
        yield 'in capitals' => ['STRICT'];
        // Which Cookie takes as leaving the attribute off altogether
        yield 'empty' => [''];
    }

    #[DataProvider('sameSiteProvider')]
    public function test_same_site_accepts_what_the_cookie_accepts(string $sameSite): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'cookie' => ['enabled' => true, 'same_site' => $sameSite],
            ],
        ]);
    }

    public function test_same_site_rejects_a_value_the_cookie_would_throw_on(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'cookie' => ['enabled' => true, 'same_site' => 'sometimes'],
                ],
            ],
            'must be one of "none", "lax" or "strict"'
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function uselessTtlProvider(): iterable
    {
        // What somebody reaching for an unlimited token tries first, since that is the convention
        // elsewhere. Here it means the token has expired by the time it is handed over
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('uselessTtlProvider')]
    public function test_a_ttl_that_issues_expired_tokens_is_rejected(int $ttl): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'ttl' => $ttl,
                ],
            ],
            'must be a positive number of seconds'
        );
    }

    public function test_a_ttl_can_be_as_long_as_the_application_wants(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                // Ten years, which is how a token that outlives the application is configured
                'ttl' => 315360000,
            ],
        ]);
    }

    public function test_a_token_limit_below_one_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'max_tokens_per_user' => 0,
                ],
            ],
            'must be at least 1'
        );
    }

    public function test_there_is_no_token_limit_unless_one_is_asked_for(): void
    {
        $this->assertProcessedConfigurationEquals(
            [[]],
            ['max_tokens_per_user' => null],
            'max_tokens_per_user'
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

    /**
     * It runs DDL while serving traffic, so it stays off unless it is asked for.
     */
    public function test_dbal_auto_create_table_defaults_to_false(): void
    {
        $this->assertProcessedConfigurationEquals(
            [
                [],
            ],
            ['dbal_auto_create_table' => false],
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

    public function test_dbal_table_name_with_invalid_sql_identifier_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_table_name' => 'tokens; DROP TABLE users--',
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_table_name_starting_with_number_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_table_name' => '123_tokens',
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_table_name_with_special_characters_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_table_name' => 'tokens-with-dashes',
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_table_name_with_valid_underscores_and_numbers_is_accepted(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.default_connection',
                'dbal_table_name' => 'my_tokens_2024',
            ],
        ]);
    }

    public function test_dbal_column_name_with_invalid_sql_identifier_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_columns' => [
                        'id' => ['name' => 'id; DROP TABLE--', 'type' => 'integer'],
                    ],
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_column_name_starting_with_number_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_columns' => [
                        'id' => ['name' => '1_id', 'type' => 'integer'],
                    ],
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_column_name_with_special_characters_is_rejected(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'refresh_token_class' => RefreshToken::class,
                    'dbal_connection' => 'doctrine.dbal.default_connection',
                    'dbal_columns' => [
                        'id' => ['name' => 'my-column', 'type' => 'integer'],
                    ],
                ],
            ],
            'must be a valid SQL identifier'
        );
    }

    public function test_dbal_column_name_with_valid_underscores_and_numbers_is_accepted(): void
    {
        $this->assertConfigurationIsValid([
            [
                'refresh_token_class' => RefreshToken::class,
                'dbal_connection' => 'doctrine.dbal.default_connection',
                'dbal_columns' => [
                    'id' => ['name' => 'my_column_2024', 'type' => 'integer'],
                    'refreshToken' => ['name' => '_token', 'type' => 'string'],
                ],
            ],
        ]);
    }
}
