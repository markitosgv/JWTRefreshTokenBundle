<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Cache\CacheRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Session\SessionLister;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    $services->set($vendor.'.refresh_token_manager')
        ->class(CacheRefreshTokenManager::class)
        ->public()
        ->args([
            service(param($vendor.'.cache_pool')),
            param($vendor.'.refresh_token.class'),
        ]);

    $services->alias(RefreshTokenManagerInterface::class, $vendor.'.refresh_token_manager');

    // services.php aliases the List and Revoke interfaces to whatever the backend put behind
    // `.refresh_token_manager`, which here cannot answer either of them. They are taken away rather
    // than left pointing at it, so a service asking for one fails to wire instead of being handed a
    // manager that would throw the first time it was called
    $services->remove(ListRefreshTokenManagerInterface::class);
    $services->remove(RevokeRefreshTokenManagerInterface::class);
    $services->remove(FamilyRefreshTokenManagerInterface::class);

    // Listing sessions is grouping a user's tokens by chain, and neither query is one a pool can be
    // asked. The service goes with them rather than being left to fail on its first call
    $services->remove($vendor.'.session_lister');
    $services->remove(SessionLister::class);
};
