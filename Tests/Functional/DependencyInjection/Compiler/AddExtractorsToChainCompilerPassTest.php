<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Compiler;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\AddExtractorsToChainCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class AddExtractorsToChainCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function test_extractors_are_added_to_the_chain(): void
    {
        $this->registerService('gesdinet_jwt_refresh_token.request.extractor.chain', ChainExtractor::class);
        $this->registerService('test.extractor', ExtractorInterface::class)
            ->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $this->compile();

        $this->assertContainerBuilderHasService('test.extractor', ExtractorInterface::class);
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'gesdinet_jwt_refresh_token.request.extractor.chain',
            'addExtractor',
            [new Reference('test.extractor')]
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddExtractorsToChainCompilerPass());
    }
}
