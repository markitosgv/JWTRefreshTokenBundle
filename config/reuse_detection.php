<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\CacheSpentRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\RefreshTokenReuseDetector;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    // Both services exist only when reuse detection is turned on. The authenticator and the listener
    // ask for them with nullOnInvalid(), so leaving them undefined is what turns the feature off
    $services->set($vendor.'.spent_refresh_token_registry')
        ->class(CacheSpentRefreshTokenRegistry::class)
        ->args([
            // An alias the extension points at the configured service. A `%parameter%` inside a
            // service reference is never resolved, so the id cannot be passed as one
            service($vendor.'.reuse_detection.cache'),
            param($vendor.'.reuse_detection.ttl'),
        ]);

    $services->set($vendor.'.refresh_token_reuse_detector')
        ->class(RefreshTokenReuseDetector::class)
        ->args([
            service($vendor.'.spent_refresh_token_registry'),
            service($vendor.'.refresh_token_manager'),
            service('event_dispatcher'),
        ]);
};
