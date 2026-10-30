<?php

declare(strict_types=1);

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
            // An alias the extension points at the configured service. A `%parameter%` inside a
            // service reference is never resolved, so the id cannot be passed as one
            service($vendor.'.rate_limiter.limiter'),
            param($vendor.'.rate_limiter.key'),
        ]);
};
