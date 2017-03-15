<?php

namespace Gesdinet\JWTRefreshTokenBundle;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\CustomUserProviderCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\DoctrineMappingsCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ObjectManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\UserCheckerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GesdinetJWTRefreshTokenBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new CustomUserProviderCompilerPass());
        $container->addCompilerPass(new ObjectManagerCompilerPass());
        $container->addCompilerPass(new DoctrineMappingsCompilerPass());
        $container->addCompilerPass(new UserCheckerCompilerPass());
    }
}
