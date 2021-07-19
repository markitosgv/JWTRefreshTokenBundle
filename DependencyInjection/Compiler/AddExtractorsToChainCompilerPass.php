<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AddExtractorsToChainCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('gesdinet.jwtrefreshtoken.request.extractor.chain')) {
            return;
        }

        $definition = $container->getDefinition('gesdinet.jwtrefreshtoken.request.extractor.chain');

        foreach ($this->findAndSortTaggedServices('gesdinet_jwt_refresh_token.request_extractor', $container) as $extractorService) {
            $definition->addMethodCall('addExtractor', [$extractorService]);
        }
    }
}
