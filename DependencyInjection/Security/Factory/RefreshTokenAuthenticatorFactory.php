<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

final class RefreshTokenAuthenticatorFactory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config, string $userProviderId, ?string $defaultEntryPointId): array
    {
        // Does not support the legacy authentication system
        return [];
    }

    public function getPosition(): string
    {
        return 'http';
    }

    public function getKey(): string
    {
        return 'refresh-jwt';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        // no-op TTL and param configuration until bundle is further updated to support per-authenticator configuration
        $node
            ->children()
                ->scalarNode('provider')->end()
                ->scalarNode('success_handler')->end()
                ->scalarNode('failure_handler')->end()
                /*
                ->integerNode('ttl')
                    ->defaultNull()
                    ->info('Sets a TTL specific to this authenticator, if not set then the "ttl" bundle config is used.')
                ->end()
                ->booleanNode('ttl_update')
                    ->defaultNull()
                    ->info('Sets whether the TTL for refresh tokens should be refreshed for this authenticator, if not set then the "ttl_update" bundle config is used.')
                ->end()
                ->scalarNode('token_parameter_name')
                    ->defaultNull()
                    ->info('Sets the parameter name for the refresh token for this authenticator, if not set then the "token_parameter_name" bundle config is used.')
                ->end()
                */
            ->end()
        ;
    }

    /**
     * @return string
     */
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId)
    {
        $authenticatorId = 'security.authenticator.refresh_jwt.'.$firewallName;

        // When per-authenticator configuration is supported, this array should be updated to check the $config values before falling back to the bundle parameters
        $options = [
            'ttl' => new Parameter('gesdinet_jwt_refresh_token.ttl'),
            'ttl_update' => new Parameter('gesdinet_jwt_refresh_token.ttl_update'),
            'token_parameter_name' => new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
        ];

        $container->setDefinition($authenticatorId, new ChildDefinition('gesdinet.jwtrefreshtoken.security.refresh_token_authenticator'))
            ->replaceArgument(3, new Reference($userProviderId))
            ->replaceArgument(4, new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)))
            ->replaceArgument(5, new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)))
            ->replaceArgument(6, $options);

        return $authenticatorId;
    }

    private function createAuthenticationSuccessHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $successHandlerId = $this->getSuccessHandlerId($id);

        if (isset($config['success_handler'])) {
            $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.custom_success_handler'))
                ->replaceArgument(0, new Reference($config['success_handler']))
                ->replaceArgument(1, [])
                ->replaceArgument(2, $id);
        } else {
            $container->setDefinition($successHandlerId, new ChildDefinition('gesdinet.jwtrefreshtoken.security.authentication.success_handler'))
                ->addMethodCall('setFirewallName', [$id]);
        }

        return $successHandlerId;
    }

    private function createAuthenticationFailureHandler(ContainerBuilder $container, string $id, array $config): string
    {
        $failureHandlerId = $this->getFailureHandlerId($id);

        if (isset($config['failure_handler'])) {
            $container->setDefinition($failureHandlerId, new ChildDefinition('security.authentication.custom_failure_handler'))
                ->replaceArgument(0, new Reference($config['failure_handler']))
                ->replaceArgument(1, []);
        } else {
            $container->setDefinition($failureHandlerId, new ChildDefinition('gesdinet.jwtrefreshtoken.security.authentication.failure_handler'));
        }

        return $failureHandlerId;
    }

    private function getSuccessHandlerId(string $id): string
    {
        return 'security.authentication.success_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }

    private function getFailureHandlerId(string $id): string
    {
        return 'security.authentication.failure_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }
}
