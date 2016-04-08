<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * CustomUserProviderCompilerPass.
 */
final class CustomEntityManagerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $customEntityManagerId = $container->getParameter('gesdinet.jwtrefreshtoken.entity_manager.id');
        if (!$customEntityManagerId) {
            return;
        }

        //replace the base alias
        $container->setAlias('gesdinet.jwtrefreshtoken.entity_manager', $customEntityManagerId);
    }
}
