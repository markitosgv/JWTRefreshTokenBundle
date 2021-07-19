<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ObjectManagerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var string|null $objectManagerId */
        $objectManagerId = $container->getParameter('gesdinet.jwtrefreshtoken.object_manager.id');
        if (!$objectManagerId) {
            return;
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.object_manager', $objectManagerId);
    }
}
