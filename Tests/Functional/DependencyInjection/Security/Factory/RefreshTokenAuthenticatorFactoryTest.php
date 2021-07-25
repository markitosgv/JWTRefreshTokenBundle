<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Security\Factory;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

final class RefreshTokenAuthenticatorFactoryTest extends TestCase
{
    /**
     * @var RefreshTokenAuthenticatorFactory
     */
    private $factory;

    /**
     * @var ContainerBuilder
     */
    private $container;

    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(RememberMeHandlerInterface::class)) {
            self::markTestSkipped('Only applies to Symfony 5.3+');
        }
    }

    protected function setUp(): void
    {
        $this->factory = new RefreshTokenAuthenticatorFactory();
        $this->container = new ContainerBuilder();
    }

    public function test_authenticator_service_is_created_with_default_configuration(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            [],
            'app.user_provider'
        );

        $this->assertTrue($this->container->hasDefinition('security.authenticator.refresh_jwt.test'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.success_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.failure_handler.test.refresh_jwt'));

        /** @var ChildDefinition $successHandler */
        $successHandler = $this->container->getDefinition('security.authentication.success_handler.test.refresh_jwt');
        $this->assertSame('gesdinet.jwtrefreshtoken.security.authentication.success_handler', $successHandler->getParent());

        /** @var ChildDefinition $failureHandler */
        $failureHandler = $this->container->getDefinition('security.authentication.failure_handler.test.refresh_jwt');
        $this->assertSame('gesdinet.jwtrefreshtoken.security.authentication.failure_handler', $failureHandler->getParent());
    }

    public function test_authenticator_service_is_created_with_custom_handlers(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            [
                'success_handler' => 'app.security.authentication.success_handler',
                'failure_handler' => 'app.security.authentication.failure_handler',
            ],
            'app.user_provider'
        );

        $this->assertTrue($this->container->hasDefinition('security.authenticator.refresh_jwt.test'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.success_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.failure_handler.test.refresh_jwt'));

        /** @var ChildDefinition $successHandler */
        $successHandler = $this->container->getDefinition('security.authentication.success_handler.test.refresh_jwt');
        $this->assertSame('security.authentication.custom_success_handler', $successHandler->getParent());

        /** @var Reference $wrappedSuccessHandler */
        $wrappedSuccessHandler = $successHandler->getArgument(0);
        $this->assertSame('app.security.authentication.success_handler', (string) $wrappedSuccessHandler);

        /** @var ChildDefinition $failureHandler */
        $failureHandler = $this->container->getDefinition('security.authentication.failure_handler.test.refresh_jwt');
        $this->assertSame('security.authentication.custom_failure_handler', $failureHandler->getParent());

        /** @var Reference $wrappedFailureHandler */
        $wrappedFailureHandler = $failureHandler->getArgument(0);
        $this->assertSame('app.security.authentication.failure_handler', (string) $wrappedFailureHandler);
    }
}
