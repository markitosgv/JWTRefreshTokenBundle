<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Compiler;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateObjectManagerCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class ValidateObjectManagerCompilerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateObjectManagerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    public function test_says_nothing_when_the_object_manager_exists(): void
    {
        $this->registerService('doctrine.orm.default_entity_manager', \stdClass::class);
        $this->container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine.orm.default_entity_manager');

        $this->compile();

        $this->addToAssertionCount(1);
    }

    /**
     * The name an entity manager is configured under is not its service id, and that is the mistake
     * this exists to name. Symfony's own message is about replacing an alias with a definition,
     * which does not hint at what to write instead.
     */
    public function test_names_the_mistake_when_the_name_was_used_instead_of_the_service(): void
    {
        $this->registerService('doctrine.orm.entity_manager_2_entity_manager', \stdClass::class);
        $this->container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'entity_manager_2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('takes a service id rather than the name an entity manager is configured under');

        $this->compile();
    }

    public function test_lists_the_object_managers_there_are(): void
    {
        $this->registerService('doctrine.orm.default_entity_manager', \stdClass::class);
        $this->registerService('doctrine.orm.custom_em_entity_manager', \stdClass::class);
        $this->registerService('doctrine_mongodb.odm.default_document_manager', \stdClass::class);
        $this->container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'custom_em');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('doctrine.orm.custom_em_entity_manager, doctrine.orm.default_entity_manager, doctrine_mongodb.odm.default_document_manager');

        $this->compile();
    }

    public function test_says_nothing_when_no_object_manager_is_configured(): void
    {
        $this->compile();

        $this->addToAssertionCount(1);
    }
}
