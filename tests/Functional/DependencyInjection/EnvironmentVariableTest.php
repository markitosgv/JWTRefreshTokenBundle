<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\GesdinetJWTRefreshTokenExtension;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * Configuration read from environment variables.
 *
 * An environment variable is not a value at compile time, it is a placeholder, and the container is
 * compiled a second time with a sample value of the right type in its place to check the
 * configuration would accept it. The sample for a string is the empty string, so a node accepting a
 * fixed list of words rejects every environment variable put in front of it, whatever the variable
 * holds.
 *
 * This is compiled the way the kernel compiles it, rather than by calling the extension, because
 * that second pass is where the rejection happened.
 */
final class EnvironmentVariableTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cookieSettingProvider(): iterable
    {
        yield 'same_site' => ['same_site', 'strict'];
        yield 'path' => ['path', '/api'];
        yield 'domain' => ['domain', 'example.com'];
    }

    #[DataProvider('cookieSettingProvider')]
    public function test_a_cookie_setting_can_be_read_from_the_environment(string $setting, string $value): void
    {
        $container = $this->containerWith(['enabled' => true, $setting => '%env(A_COOKIE_SETTING)%']);

        $_ENV['A_COOKIE_SETTING'] = $value;

        try {
            $container->compile(true);
        } finally {
            unset($_ENV['A_COOKIE_SETTING']);
        }

        /** @var array<string, mixed> $cookie */
        $cookie = $container->getParameter('gesdinet_jwt_refresh_token.cookie');

        $this->assertSame($value, $cookie[$setting], 'The variable should reach the listener resolved, not as a placeholder');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function numericSettingProvider(): iterable
    {
        yield 'ttl' => ['ttl', '86400'];
        yield 'max_tokens_per_user' => ['max_tokens_per_user', '5'];
    }

    /**
     * A number checked with a validate() closure rejects every environment variable put in front of
     * it: the container is compiled again with a sample value of the type in place, and for an int
     * that sample is 0. `min()` is skipped while a placeholder is being handled, a closure is not.
     */
    #[DataProvider('numericSettingProvider')]
    public function test_a_numeric_setting_can_be_read_from_the_environment(string $setting, string $value): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->registerExtension(new GesdinetJWTRefreshTokenExtension());
        $container->loadFromExtension('gesdinet_jwt_refresh_token', [
            'refresh_token_class' => RefreshToken::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            $setting => '%env(int:A_NUMERIC_SETTING)%',
        ]);

        foreach (['doctrine.orm.entity_manager', 'request_stack', 'event_dispatcher', 'security.http_utils', 'lexik_jwt_authentication.handler.authentication_success'] as $id) {
            $container->register($id, \stdClass::class)->setPublic(true);
        }

        $_ENV['A_NUMERIC_SETTING'] = $value;

        try {
            $container->compile(true);
        } finally {
            unset($_ENV['A_NUMERIC_SETTING']);
        }

        $this->assertSame((int) $value, $container->getParameter('gesdinet_jwt_refresh_token.'.$setting));
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function containerWith(array $cookie): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->registerExtension(new GesdinetJWTRefreshTokenExtension());
        $container->loadFromExtension('gesdinet_jwt_refresh_token', [
            'refresh_token_class' => RefreshToken::class,
            'object_manager' => 'doctrine.orm.entity_manager',
            'cookie' => $cookie,
        ]);

        foreach (['doctrine.orm.entity_manager', 'request_stack', 'event_dispatcher', 'security.http_utils', 'lexik_jwt_authentication.handler.authentication_success'] as $id) {
            $container->register($id, \stdClass::class)->setPublic(true);
        }

        return $container;
    }
}
