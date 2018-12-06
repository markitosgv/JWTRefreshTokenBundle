<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * UserCheckerCompilerPass.
 */
final class UserCheckerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $userCheckerId = $container->getParameter('gesdinet.jwtrefreshtoken.user_checker.id');
        if (!$userCheckerId) {
            return;
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.user_checker', $userCheckerId);
    }
}
