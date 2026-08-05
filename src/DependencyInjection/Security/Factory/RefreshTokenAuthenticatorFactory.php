<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory;

use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class RefreshTokenAuthenticatorFactory implements AuthenticatorFactoryInterface
{
    /**
     * Above the JWT authenticator, which sits at -50.
     *
     * Symfony orders authenticators by this rather than by the order they appear on the firewall,
     * so with both on one firewall the JWT authenticator used to be reached first and rejected an
     * expired token before this one could exchange it — which is the whole point of the refresh
     * endpoint. Reordering them in security.yaml has no effect, as the priority is what decides.
     *
     * Being tried first costs nothing elsewhere: supports() answers on the configured path alone,
     * so on every other request this authenticator declines and the next one runs as before.
     *
     * @psalm-pure
     */
    #[\Override]
    public function getPriority(): int
    {
        return -5;
    }

    /**
     * @psalm-pure
     */
    #[\Override]
    public function getKey(): string
    {
        return 'refresh-jwt';
    }

    #[\Override]
    public function addConfiguration(NodeDefinition $builder): void
    {
        if (!$builder instanceof ArrayNodeDefinition) {
            throw new InvalidArgumentException(sprintf('The "%s" authenticator can only be configured on an array node, "%s" given.', $this->getKey(), get_debug_type($builder)));
        }

        $builder
            ->children()
                ->scalarNode('check_path')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The path the refresh endpoint answers on, as a path or a route name. The authenticator only takes over requests matching it, so it has to be the route you defined for refreshing.')
                ->end()
                ->scalarNode('provider')->end()
                ->scalarNode('success_handler')->end()
                ->scalarNode('failure_handler')->end()
                ->booleanNode('invalidate_token_on_logout')
                    ->defaultTrue()
                    ->info('When enabled, the refresh token will be invalided on logout.')
                ->end()
                // Every one of these defaults to null, meaning "whatever the bundle is configured
                // with". Null is what distinguishes not saying anything from saying the same thing,
                // which is what lets one firewall differ while the rest follow the default
                ->integerNode('ttl')
                    ->defaultNull()
                    ->min(1)
                    ->info('How long a refresh token issued on this firewall lasts, in seconds. Falls back to the bundle\'s "ttl".')
                ->end()
                ->booleanNode('ttl_update')
                    ->defaultNull()
                    ->info('Whether using a refresh token on this firewall starts its ttl over. Falls back to the bundle\'s "ttl_update".')
                ->end()
                ->scalarNode('token_parameter_name')
                    ->defaultNull()
                    ->cannotBeEmpty()
                    ->info('The request parameter carrying the refresh token on this firewall. Falls back to the bundle\'s "token_parameter_name".')
                ->end()
                ->booleanNode('single_use')
                    ->defaultNull()
                    ->info('Whether a refresh on this firewall replaces the token it used. Falls back to the bundle\'s "single_use".')
                ->end()
                ->booleanNode('single_use_ttl_update')
                    ->defaultNull()
                    ->info('Whether a token issued in place of a single use one on this firewall starts its ttl over. Falls back to the bundle\'s "single_use_ttl_update".')
                ->end()
                ->integerNode('max_session_lifetime')
                    ->defaultNull()
                    ->min(1)
                    ->info('How long a chain of refreshes on this firewall may go on for, in seconds. Falls back to the bundle\'s "max_session_lifetime".')
                ->end()
                ->integerNode('max_tokens_per_user')
                    ->defaultNull()
                    ->min(1)
                    ->info('How many refresh tokens a user may hold at once on this firewall. Falls back to the bundle\'s "max_tokens_per_user".')
                ->end()
                ->booleanNode('return_expiration')
                    ->defaultNull()
                    ->info('Whether the response on this firewall carries the token expiry. Falls back to the bundle\'s "return_expiration".')
                ->end()
                ->scalarNode('return_expiration_parameter_name')
                    ->defaultNull()
                    ->cannotBeEmpty()
                    ->info('The response field carrying the expiry on this firewall. Falls back to the bundle\'s "return_expiration_parameter_name".')
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{check_path: string, provider?: string, success_handler?: string, failure_handler?: string, invalidate_token_on_logout: bool, ttl?: int|null, ttl_update?: bool|null, token_parameter_name?: string|null, single_use?: bool|null, single_use_ttl_update?: bool|null, max_session_lifetime?: int|null, max_tokens_per_user?: int|null, return_expiration?: bool|null, return_expiration_parameter_name?: string|null} $config
     */
    #[\Override]
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $authenticatorId = 'security.authenticator.refresh_jwt.'.$firewallName;

        $this->recordCheckPath($container, $config['check_path']);
        $this->recordFirewallOptions($container, $firewallName, $config);

        $options = [
            'check_path' => $config['check_path'],
            'ttl' => $config['ttl'] ?? new Parameter('gesdinet_jwt_refresh_token.ttl'),
            'ttl_update' => $config['ttl_update'] ?? new Parameter('gesdinet_jwt_refresh_token.ttl_update'),
            'token_parameter_name' => $config['token_parameter_name'] ?? new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
        ];

        $container->setDefinition($authenticatorId, new ChildDefinition('gesdinet_jwt_refresh_token.security.refresh_token_authenticator'))
            ->replaceArgument(3, new Reference($userProviderId))
            ->replaceArgument(4, new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)))
            ->replaceArgument(5, new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)))
            ->replaceArgument(6, $options);

        if ($config['invalidate_token_on_logout']) {
            $container->setDefinition('gesdinet_jwt_refresh_token.security.listener.logout.'.$firewallName, new ChildDefinition('gesdinet_jwt_refresh_token.security.listener.logout'))
                ->addTag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onLogout', 'dispatcher' => 'security.event_dispatcher.'.$firewallName]);
        }

        return $authenticatorId;
    }

    /**
     * Keeps the refresh paths where something outside the firewall can reach them.
     *
     * The path is only ever named here, on the firewall, so documenting the endpoint means
     * collecting it as each authenticator is created. A firewall may be configured more than once,
     * hence the check for one already recorded.
     */
    private function recordCheckPath(ContainerBuilder $container, string $checkPath): void
    {
        /** @var string[] $checkPaths */
        $checkPaths = $container->hasParameter('gesdinet_jwt_refresh_token.check_paths')
            ? (array) $container->getParameter('gesdinet_jwt_refresh_token.check_paths')
            : [];

        if (in_array($checkPath, $checkPaths, true)) {
            return;
        }

        $checkPaths[] = $checkPath;

        $container->setParameter('gesdinet_jwt_refresh_token.check_paths', $checkPaths);
    }

    /**
     * Options set on one firewall, where the success listener can find them.
     *
     * The listener runs on Lexik's authentication success event, which is handed a user and the
     * response data and knows nothing about the firewall — and on a login it is Lexik's authenticator
     * that ran, not this one, so nothing can be carried across from here. What the listener does have
     * is the request, and the firewall map turns a request into a firewall name. This is the other
     * half of that lookup.
     *
     * Only options actually set are recorded. An option left out has to stay distinguishable from
     * one set to the same value as the default, or a firewall could never follow a default that
     * changed.
     *
     * @param array<string, mixed> $config
     */
    private function recordFirewallOptions(ContainerBuilder $container, string $firewallName, array $config): void
    {
        $overridable = [
            'ttl',
            'ttl_update',
            'token_parameter_name',
            'single_use',
            'single_use_ttl_update',
            'max_session_lifetime',
            'max_tokens_per_user',
            'return_expiration',
            'return_expiration_parameter_name',
        ];

        $options = [];

        foreach ($overridable as $option) {
            if (null !== ($config[$option] ?? null)) {
                $options[$option] = $config[$option];
            }
        }

        /** @var array<string, array<string, mixed>> $firewalls */
        $firewalls = $container->hasParameter('gesdinet_jwt_refresh_token.firewall_options')
            ? (array) $container->getParameter('gesdinet_jwt_refresh_token.firewall_options')
            : [];

        // A firewall may be configured more than once, so what is already there is kept rather than
        // replaced by a later pass that saw nothing
        $firewalls[$firewallName] = $options + ($firewalls[$firewallName] ?? []);

        $container->setParameter('gesdinet_jwt_refresh_token.firewall_options', $firewalls);
    }

    /**
     * @param array{check_path: string, provider?: string, success_handler?: string, failure_handler?: string, invalidate_token_on_logout: bool} $config
     */
    private function createAuthenticationSuccessHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $successHandlerId = $this->getSuccessHandlerId($id);

        if (isset($config['success_handler'])) {
            $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.custom_success_handler'))
                ->replaceArgument(0, new Reference($config['success_handler']))
                ->replaceArgument(1, [])
                ->replaceArgument(2, $id);
        } else {
            $container->setDefinition($successHandlerId, new ChildDefinition('gesdinet_jwt_refresh_token.security.authentication.success_handler'))
                ->addMethodCall('setFirewallName', [$id]);
        }

        return $successHandlerId;
    }

    /**
     * @param array{check_path: string, provider?: string, success_handler?: string, failure_handler?: string, invalidate_token_on_logout: bool} $config
     */
    private function createAuthenticationFailureHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $failureHandlerId = $this->getFailureHandlerId($id);

        if (isset($config['failure_handler'])) {
            $container->setDefinition($failureHandlerId, new ChildDefinition('security.authentication.custom_failure_handler'))
                ->replaceArgument(0, new Reference($config['failure_handler']))
                ->replaceArgument(1, []);
        } else {
            $container->setDefinition($failureHandlerId, new ChildDefinition('gesdinet_jwt_refresh_token.security.authentication.failure_handler'));
        }

        return $failureHandlerId;
    }

    /**
     * @psalm-mutation-free
     */
    private function getSuccessHandlerId(string $id): string
    {
        return 'security.authentication.success_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }

    /**
     * @psalm-mutation-free
     */
    private function getFailureHandlerId(string $id): string
    {
        return 'security.authentication.failure_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }
}
