<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\OpenApi\OpenApiFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    // Lexik decorates the same factory at priority 0, so a lower one is handed the specification it
    // has already contributed the login endpoint to, rather than running before it
    $services->set($vendor.'.api_platform.openapi.factory')
        ->class(OpenApiFactory::class)
        ->private()
        ->decorate('api_platform.openapi.factory', null, -25, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service($vendor.'.api_platform.openapi.factory.inner'),
            param($vendor.'.check_paths'),
            param($vendor.'.token_parameter_name'),
            param($vendor.'.return_expiration'),
            param($vendor.'.return_expiration_parameter_name'),
            param($vendor.'.cookie'),
        ]);
};
