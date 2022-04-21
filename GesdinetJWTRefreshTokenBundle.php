<?php

namespace Gesdinet\JWTRefreshTokenBundle;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\AddExtractorsToChainCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ObjectManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GesdinetJWTRefreshTokenBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddExtractorsToChainCompilerPass());
        $container->addCompilerPass(new ObjectManagerCompilerPass());

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new RefreshTokenAuthenticatorFactory());
    }
}
