<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Compiler;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateBlockedTokenManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\EventListener\BlockPreviousJWTListener;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class ValidateBlockedTokenManagerCompilerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateBlockedTokenManagerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    public function test_says_nothing_when_blocking_was_not_asked_for(): void
    {
        $this->compile();

        $this->addToAssertionCount(1);
    }

    public function test_says_nothing_when_the_blocklist_is_there(): void
    {
        $this->registerService('gesdinet_jwt_refresh_token.event_listener.block_previous_jwt', BlockPreviousJWTListener::class);
        $this->registerService('lexik_jwt_authentication.blocked_token_manager', \stdClass::class);

        $this->compile();

        $this->addToAssertionCount(1);
    }

    /**
     * The blocklist service only exists once Lexik's own option turns it on, so without this the
     * container fails on a missing id belonging to another bundle.
     */
    public function test_says_which_option_to_turn_on_when_the_blocklist_is_missing(): void
    {
        $this->registerService('gesdinet_jwt_refresh_token.event_listener.block_previous_jwt', BlockPreviousJWTListener::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('blocklist_token');

        $this->compile();
    }
}
