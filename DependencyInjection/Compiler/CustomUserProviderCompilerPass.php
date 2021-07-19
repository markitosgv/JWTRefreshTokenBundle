<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @deprecated no replacement
 */
final class CustomUserProviderCompilerPass implements CompilerPassInterface
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

        /** @var string|null $customUserProvider */
        $customUserProvider = $container->getParameter('gesdinet_jwt_refresh_token.user_provider');
        if (!$customUserProvider) {
            return;
        }
        if (!$container->hasDefinition('gesdinet.jwtrefreshtoken.user_provider')) {
            return;
        }

        $definition = $container->getDefinition('gesdinet.jwtrefreshtoken.user_provider');

        $definition->addMethodCall(
            'setCustomUserProvider',
            [new Reference($customUserProvider, ContainerInterface::NULL_ON_INVALID_REFERENCE)]
        );
    }
}
