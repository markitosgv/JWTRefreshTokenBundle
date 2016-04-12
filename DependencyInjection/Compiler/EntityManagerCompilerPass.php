<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * CustomUserProviderCompilerPass.
 */
final class EntityManagerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $entityManagerId = $container->getParameter('gesdinet.jwtrefreshtoken.entity_manager.id');
        if (!$entityManagerId) {
            return;
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.entity_manager', $entityManagerId);
    }
}
