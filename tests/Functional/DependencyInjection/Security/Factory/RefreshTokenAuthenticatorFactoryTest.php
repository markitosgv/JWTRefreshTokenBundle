<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Security\Factory;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class RefreshTokenAuthenticatorFactoryTest extends TestCase
{
    private RefreshTokenAuthenticatorFactory $factory;

    private ContainerBuilder $container;

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
            [
                'check_path' => '/login_check',
                'invalidate_token_on_logout' => true,
            ],
            'app.user_provider'
        );

        $this->assertTrue($this->container->hasDefinition('security.authenticator.refresh_jwt.test'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.success_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.failure_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('gesdinet_jwt_refresh_token.security.listener.logout.test'));

        /** @var ChildDefinition $successHandler */
        $successHandler = $this->container->getDefinition('security.authentication.success_handler.test.refresh_jwt');
        $this->assertSame('gesdinet_jwt_refresh_token.security.authentication.success_handler', $successHandler->getParent());

        /** @var ChildDefinition $failureHandler */
        $failureHandler = $this->container->getDefinition('security.authentication.failure_handler.test.refresh_jwt');
        $this->assertSame('gesdinet_jwt_refresh_token.security.authentication.failure_handler', $failureHandler->getParent());

        /** @var ChildDefinition $logoutListener */
        $logoutListener = $this->container->getDefinition('gesdinet_jwt_refresh_token.security.listener.logout.test');
        $this->assertSame(['event' => LogoutEvent::class, 'method' => 'onLogout', 'dispatcher' => 'security.event_dispatcher.test'], $logoutListener->getTags()['kernel.event_listener'][0]);
    }

    public function test_authenticator_service_is_created_with_custom_handlers(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            [
                'check_path' => '/login_check',
                'success_handler' => 'app.security.authentication.success_handler',
                'failure_handler' => 'app.security.authentication.failure_handler',
                'invalidate_token_on_logout' => true,
            ],
            'app.user_provider'
        );

        $this->assertTrue($this->container->hasDefinition('security.authenticator.refresh_jwt.test'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.success_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('security.authentication.failure_handler.test.refresh_jwt'));
        $this->assertTrue($this->container->hasDefinition('gesdinet_jwt_refresh_token.security.listener.logout.test'));

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
