<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ObjectManagerCompilerPass.
 */
final class ObjectManagerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $objectManagerId = $container->getParameter('gesdinet.jwtrefreshtoken.object_manager.id');
        if (!$objectManagerId) {
            return;
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.object_manager', $objectManagerId);
    }
}
