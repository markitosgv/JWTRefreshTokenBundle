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
            // The parameter holds a service id, so it is turned into the `%name%` placeholder
            // service() takes. The cast used to be implicit; declare(strict_types=1) makes it
            // the caller's job, which is the better place for it to be visible
            service((string) param($vendor.'.rate_limiter.limiter')),
            param($vendor.'.rate_limiter.key'),
        ]);
};
