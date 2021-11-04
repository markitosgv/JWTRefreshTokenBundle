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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, use "%s" instead.', LegacyRefreshTokenAuthenticatorFactory::class, RefreshTokenAuthenticatorFactory::class);

/**
 * @deprecated to be removed in 2.0, use `Gesdinet\JWTRefreshTokenBundle\Security\Factory\RefreshTokenAuthenticatorFactory` instead.
 */
final class LegacyRefreshTokenAuthenticatorFactory extends RefreshTokenAuthenticatorFactory implements SecurityFactoryInterface
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
}
