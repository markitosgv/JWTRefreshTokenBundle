<?php

namespace Gesdinet\JWTRefreshTokenBundle;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\CustomUserProviderCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ObjectManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\UserCheckerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

class GesdinetJWTRefreshTokenBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new CustomUserProviderCompilerPass(true));
        $container->addCompilerPass(new ObjectManagerCompilerPass());
        $container->addCompilerPass(new UserCheckerCompilerPass(true));

        // Only register the security authenticator for Symfony 5.3+
        if (interface_exists(RememberMeHandlerInterface::class)) {
            /** @var SecurityExtension $extension */
            $extension = $container->getExtension('security');
            $extension->addSecurityListenerFactory(new RefreshTokenAuthenticatorFactory());
        }
    }
}
