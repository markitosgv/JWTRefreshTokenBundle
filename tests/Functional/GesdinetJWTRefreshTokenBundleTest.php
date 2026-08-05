<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\AddExtractorsToChainCompilerPass;
use Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\RecordingSecurityExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class GesdinetJWTRefreshTokenBundleTest extends TestCase
{
    private ContainerBuilder $container;

    private RecordingSecurityExtension $securityExtension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $this->securityExtension = new RecordingSecurityExtension();

        $this->container->registerExtension($this->securityExtension);
    }

    public function test_registers_the_pass_collecting_the_tagged_extractors(): void
    {
        new GesdinetJWTRefreshTokenBundle()->build($this->container);

        $passes = array_filter(
            $this->container->getCompiler()->getPassConfig()->getPasses(),
            static fn (object $pass): bool => $pass instanceof AddExtractorsToChainCompilerPass
        );

        $this->assertCount(1, $passes, 'The extractors are collected by a compiler pass registered by the bundle');
    }

    public function test_registers_the_authenticator_factory_on_the_security_extension(): void
    {
        new GesdinetJWTRefreshTokenBundle()->build($this->container);

        $keys = array_map(
            static fn (AuthenticatorFactoryInterface $factory): string => $factory->getKey(),
            $this->securityExtension->addedFactories
        );

        $this->assertContains('refresh-jwt', $keys, 'Without the factory the refresh_jwt firewall option does not exist');
    }

    public function test_path_points_at_the_root_of_the_bundle(): void
    {
        $this->assertFileExists(new GesdinetJWTRefreshTokenBundle()->getPath().'/composer.json');
    }
}
