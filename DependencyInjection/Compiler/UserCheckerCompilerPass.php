<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated no replacement
 */
final class UserCheckerCompilerPass implements CompilerPassInterface
{
    private bool $internalUse;

    /**
     * @param bool $internalUse Flag indicating the pass was created by an internal bundle call (used to suppress runtime deprecations)
     */
    public function __construct(bool $internalUse = false)
    {
        $this->internalUse = $internalUse;
    }

    public function process(ContainerBuilder $container): void
    {
        if (false === $this->internalUse) {
            trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated.', self::class);
        }

        /** @var string|null $userCheckerId */
        $userCheckerId = $container->getParameter('gesdinet.jwtrefreshtoken.user_checker.id');
        if (!$userCheckerId) {
            return;
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.user_checker', $userCheckerId);
    }
}
