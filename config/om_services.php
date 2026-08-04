<?php

use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->set('gesdinet_jwt_refresh_token.refresh_token_manager')
        ->class(RefreshTokenManager::class)
        ->public()
        ->args([
            service('gesdinet_jwt_refresh_token.object_manager'),
            param('gesdinet_jwt_refresh_token.refresh_token.class'),
            param('gesdinet_jwt_refresh_token.default_invalid_batch_size'),
        ]);
};
