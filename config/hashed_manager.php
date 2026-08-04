<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Model\HashedRefreshTokenManager;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    // Decorating the manager rather than replacing it means this works over whichever backend is in
    // use, a manager of your own included, and every alias points at the hashing one
    $services->set($vendor.'.hashed_refresh_token_manager')
        ->class(HashedRefreshTokenManager::class)
        ->decorate($vendor.'.refresh_token_manager')
        ->args([
            service($vendor.'.hashed_refresh_token_manager.inner'),
            param($vendor.'.hash_tokens.accept_stored_in_the_clear'),
        ]);
};
