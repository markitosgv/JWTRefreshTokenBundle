<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Security\RateLimiting\RefreshRateLimiter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    // Defined only when rate_limiter is turned on; the authenticator asks for it with
    // nullOnInvalid(), so leaving it undefined is what turns the feature off
    $services->set($vendor.'.refresh_rate_limiter')
        ->class(RefreshRateLimiter::class)
        ->args([
            service(param($vendor.'.rate_limiter.limiter')),
            param($vendor.'.rate_limiter.key'),
        ]);
};
