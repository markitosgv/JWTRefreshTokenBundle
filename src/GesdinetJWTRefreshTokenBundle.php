<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\AddExtractorsToChainCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateBlockedTokenManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateDBALConnectionCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ValidateObjectManagerCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GesdinetJWTRefreshTokenBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddExtractorsToChainCompilerPass());
        $container->addCompilerPass(new ValidateDBALConnectionCompilerPass());
        $container->addCompilerPass(new ValidateObjectManagerCompilerPass());
        $container->addCompilerPass(new ValidateBlockedTokenManagerCompilerPass());

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new RefreshTokenAuthenticatorFactory());
    }

    /**
     * @psalm-pure
     */
    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
