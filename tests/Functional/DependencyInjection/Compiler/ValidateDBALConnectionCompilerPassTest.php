<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Compiler;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateDBALConnectionCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class ValidateDBALConnectionCompilerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateDBALConnectionCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    public function test_says_nothing_when_no_connection_is_configured(): void
    {
        $this->compile();

        $this->addToAssertionCount(1);
    }

    public function test_says_nothing_when_the_connection_is_there(): void
    {
        $this->registerService('doctrine.dbal.default_connection', \stdClass::class);
        $this->setParameter('gesdinet_jwt_refresh_token.dbal.connection', 'doctrine.dbal.default_connection');

        $this->compile();

        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_connection_configured_as_an_empty_string(): void
    {
        $this->setParameter('gesdinet_jwt_refresh_token.dbal.connection', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('configured but empty');

        $this->compile();
    }

    public function test_lists_the_connections_there_are_when_the_one_named_is_missing(): void
    {
        $this->registerService('doctrine.dbal.default_connection', \stdClass::class);
        $this->setParameter('gesdinet_jwt_refresh_token.dbal.connection', 'doctrine.dbal.missing_connection');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('doctrine.dbal.default_connection');

        $this->compile();
    }
}
