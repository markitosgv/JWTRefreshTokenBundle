<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Security\Factory;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
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

    public function test_is_registered_under_the_refresh_jwt_key_after_the_other_authenticators(): void
    {
        $this->assertSame('refresh-jwt', $this->factory->getKey());
        $this->assertSame(-50, $this->factory->getPriority());
    }

    public function test_firewall_configuration_defaults(): void
    {
        $this->assertSame(
            [
                'check_path' => '/login_check',
                'invalidate_token_on_logout' => true,
            ],
            $this->processConfiguration([])
        );
    }

    public function test_firewall_configuration_accepts_the_supported_values(): void
    {
        $config = [
            'check_path' => '/api/token/refresh',
            'provider' => 'app.user_provider',
            'success_handler' => 'app.security.authentication.success_handler',
            'failure_handler' => 'app.security.authentication.failure_handler',
            'invalidate_token_on_logout' => false,
        ];

        $this->assertSame($config, $this->processConfiguration($config), 'Every supported value is kept, and nothing else is added');
    }

    public function test_configuration_is_rejected_on_a_node_that_cannot_hold_children(): void
    {
        $node = (new TreeBuilder('refresh-jwt', 'scalar'))->getRootNode();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "refresh-jwt" authenticator can only be configured on an array node');

        $this->factory->addConfiguration($node);
    }

    /**
     * The path is only ever named on the firewall, so documenting the endpoint means collecting it
     * as each authenticator is created.
     */
    public function test_the_check_path_is_recorded_for_whatever_needs_it_outside_the_firewall(): void
    {
        $this->factory->createAuthenticator($this->container, 'api', $this->processConfiguration(['check_path' => '/api/token/refresh']), 'app.user_provider');
        $this->factory->createAuthenticator($this->container, 'admin', $this->processConfiguration(['check_path' => '/admin/token/refresh']), 'app.user_provider');

        $this->assertSame(
            ['/api/token/refresh', '/admin/token/refresh'],
            $this->container->getParameter('gesdinet_jwt_refresh_token.check_paths')
        );
    }

    public function test_a_path_shared_by_two_firewalls_is_recorded_once(): void
    {
        $this->factory->createAuthenticator($this->container, 'api', $this->processConfiguration(['check_path' => '/api/token/refresh']), 'app.user_provider');
        $this->factory->createAuthenticator($this->container, 'other', $this->processConfiguration(['check_path' => '/api/token/refresh']), 'app.user_provider');

        $this->assertSame(['/api/token/refresh'], $this->container->getParameter('gesdinet_jwt_refresh_token.check_paths'));
    }

    public function test_authenticator_service_takes_its_options_from_the_bundle_parameters(): void
    {
        $authenticatorId = $this->factory->createAuthenticator(
            $this->container,
            'test',
            $this->processConfiguration([]),
            'app.user_provider'
        );

        $this->assertSame('security.authenticator.refresh_jwt.test', $authenticatorId);

        /** @var ChildDefinition $authenticator */
        $authenticator = $this->container->getDefinition($authenticatorId);

        $this->assertSame('gesdinet_jwt_refresh_token.security.refresh_token_authenticator', $authenticator->getParent());
        $this->assertEquals(new Reference('app.user_provider'), $authenticator->getArgument(3));

        // Per authenticator options are not supported yet, so everything but the path is taken
        // from the bundle parameters
        $this->assertEquals(
            [
                'check_path' => '/login_check',
                'ttl' => new Parameter('gesdinet_jwt_refresh_token.ttl'),
                'ttl_update' => new Parameter('gesdinet_jwt_refresh_token.ttl_update'),
                'token_parameter_name' => new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
            ],
            $authenticator->getArgument(6)
        );
    }

    public function test_success_handler_is_bound_to_the_firewall(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            $this->processConfiguration([]),
            'app.user_provider'
        );

        $successHandler = $this->container->getDefinition('security.authentication.success_handler.test.refresh_jwt');

        $this->assertSame([['setFirewallName', ['test']]], $successHandler->getMethodCalls());
    }

    public function test_logout_listener_is_not_registered_when_the_token_is_kept_on_logout(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            $this->processConfiguration(['invalidate_token_on_logout' => false]),
            'app.user_provider'
        );

        $this->assertTrue($this->container->hasDefinition('security.authenticator.refresh_jwt.test'));
        $this->assertFalse($this->container->hasDefinition('gesdinet_jwt_refresh_token.security.listener.logout.test'));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{check_path: string, provider?: string, success_handler?: string, failure_handler?: string, invalidate_token_on_logout: bool}
     */
    private function processConfiguration(array $config): array
    {
        $treeBuilder = new TreeBuilder('refresh-jwt');

        $this->factory->addConfiguration($treeBuilder->getRootNode());

        /** @var array{check_path: string, provider?: string, success_handler?: string, failure_handler?: string, invalidate_token_on_logout: bool} $processed */
        $processed = (new Processor())->process($treeBuilder->buildTree(), [$config]);

        return $processed;
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
        $this->assertSame(
            [
                'kernel.event_listener' => [
                    ['event' => LogoutEvent::class, 'method' => 'onLogout', 'dispatcher' => 'security.event_dispatcher.test'],
                ],
            ],
            $logoutListener->getTags()
        );
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
