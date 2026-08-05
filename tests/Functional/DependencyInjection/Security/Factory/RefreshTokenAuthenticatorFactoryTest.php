<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\DependencyInjection\Security\Factory;

use Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory\RefreshTokenAuthenticatorFactory;
use Lexik\Bundle\JWTAuthenticationBundle\DependencyInjection\Security\Factory\JWTAuthenticatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

    /**
     * It used to default to /login_check, Lexik's login path, which is never the right one for
     * refreshing: left alone the authenticator took no requests and the router reported the refresh
     * route as having no controller. Requiring it turns that into a configuration error.
     */
    public function test_the_check_path_has_to_be_given(): void
    {
        $treeBuilder = new TreeBuilder('refresh-jwt');

        $this->factory->addConfiguration($treeBuilder->getRootNode());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('check_path');

        (new Processor())->process($treeBuilder->buildTree(), [[]]);
    }

    public function test_is_registered_under_the_refresh_jwt_key(): void
    {
        $this->assertSame('refresh-jwt', $this->factory->getKey());
    }

    /**
     * Symfony orders authenticators by factory priority, not by the order they are written on the
     * firewall, so this is what decides whether an expired JWT can be exchanged at all: reached
     * second, the JWT authenticator rejects the request before the refresh one sees it.
     *
     * Compared against Lexik's own factory rather than a number, so that this fails if they move.
     */
    public function test_is_tried_before_the_jwt_authenticator(): void
    {
        $this->assertGreaterThan(
            (new JWTAuthenticatorFactory())->getPriority(),
            $this->factory->getPriority()
        );
    }

    /**
     * Every overridable option defaults to null rather than to the bundle's value. Null is what
     * distinguishes a firewall saying nothing from one saying the same thing as the default, and it
     * is what lets a firewall follow a default that later changes.
     */
    public function test_firewall_configuration_defaults(): void
    {
        $this->assertSame(
            [
                'check_path' => '/api/token/refresh',
                'invalidate_token_on_logout' => true,
                'ttl' => null,
                'ttl_update' => null,
                'token_parameter_name' => null,
                'single_use' => null,
                'single_use_ttl_update' => null,
                'max_session_lifetime' => null,
                'max_tokens_per_user' => null,
                'return_expiration' => null,
                'return_expiration_parameter_name' => null,
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
            'ttl' => 3600,
            'ttl_update' => true,
            'token_parameter_name' => 'rt',
            'single_use' => true,
            'single_use_ttl_update' => false,
            'max_session_lifetime' => 604800,
            'max_tokens_per_user' => 3,
            'return_expiration' => true,
            'return_expiration_parameter_name' => 'rt_exp',
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

    /**
     * The other half of the firewall lookup: the success listener runs on Lexik's event, which knows
     * nothing about firewalls, so it reads what was recorded here against the name the firewall map
     * gives it.
     */
    public function test_only_the_options_a_firewall_actually_set_are_recorded(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'internal',
            $this->processConfiguration(['check_path' => '/internal/token/refresh', 'ttl' => 3600, 'single_use' => true]),
            'app.user_provider'
        );
        $this->factory->createAuthenticator(
            $this->container,
            'customers',
            $this->processConfiguration(['check_path' => '/api/token/refresh']),
            'app.user_provider'
        );

        $this->assertSame(
            [
                'internal' => ['ttl' => 3600, 'single_use' => true],
                // Nothing was said, so nothing is recorded and every value stays the bundle's. An
                // option recorded as its current default would freeze this firewall on that value
                'customers' => [],
            ],
            $this->container->getParameter('gesdinet_jwt_refresh_token.firewall_options')
        );
    }

    /**
     * A firewall may be configured more than once, and a later pass that saw nothing must not undo
     * what an earlier one recorded.
     */
    public function test_a_firewall_configured_twice_keeps_what_was_already_recorded(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'api',
            $this->processConfiguration(['check_path' => '/api/token/refresh', 'ttl' => 3600]),
            'app.user_provider'
        );
        $this->factory->createAuthenticator(
            $this->container,
            'api',
            $this->processConfiguration(['check_path' => '/api/token/refresh', 'single_use' => true]),
            'app.user_provider'
        );

        $this->assertSame(
            ['api' => ['single_use' => true, 'ttl' => 3600]],
            $this->container->getParameter('gesdinet_jwt_refresh_token.firewall_options')
        );
    }

    /**
     * The authenticator reads its own three from the options array rather than from the recorded
     * map, so a firewall saying something has to reach it there too.
     */
    public function test_the_authenticator_is_given_what_its_firewall_asked_for(): void
    {
        $authenticatorId = $this->factory->createAuthenticator(
            $this->container,
            'internal',
            $this->processConfiguration([
                'check_path' => '/internal/token/refresh',
                'ttl' => 3600,
                'ttl_update' => true,
                'token_parameter_name' => 'rt',
            ]),
            'app.user_provider'
        );

        $options = $this->container->getDefinition($authenticatorId)->getArgument(6);

        $this->assertIsArray($options);
        $this->assertSame(3600, $options['ttl']);
        $this->assertTrue($options['ttl_update']);
        $this->assertSame('rt', $options['token_parameter_name']);
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
                'check_path' => '/api/token/refresh',
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
        $processed = (new Processor())->process($treeBuilder->buildTree(), [$config + ['check_path' => '/api/token/refresh']]);

        return $processed;
    }

    public function test_authenticator_service_is_created_with_default_configuration(): void
    {
        $this->factory->createAuthenticator(
            $this->container,
            'test',
            [
                'check_path' => '/api/token/refresh',
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
                'check_path' => '/api/token/refresh',
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
