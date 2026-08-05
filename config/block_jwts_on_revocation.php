<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\EventListener\RejectJWTsIssuedBeforeRevocationListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RevocationRecordingRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\CacheJWTRevocationRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Events;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    $services->set($vendor.'.jwt_revocation_registry')
        ->class(CacheJWTRevocationRegistry::class)
        ->args([
            // The parameter holds a service id, so it is turned into the `%name%` placeholder
            // service() takes. The cast used to be implicit; declare(strict_types=1) makes it
            // the caller's job, which is the better place for it to be visible
            service((string) param($vendor.'.block_jwts_on_revocation.cache')),
            param($vendor.'.block_jwts_on_revocation.ttl'),
        ]);

    // Decorating rather than replacing means this works over whichever backend is in use, a manager
    // of your own included, and every alias points at the recording one
    $services->set($vendor.'.revocation_recording_refresh_token_manager')
        ->class(RevocationRecordingRefreshTokenManager::class)
        ->decorate($vendor.'.refresh_token_manager')
        ->args([
            service($vendor.'.revocation_recording_refresh_token_manager.inner'),
            service($vendor.'.jwt_revocation_registry'),
        ]);

    $services->set($vendor.'.event_listener.reject_jwts_issued_before_revocation')
        ->class(RejectJWTsIssuedBeforeRevocationListener::class)
        ->args([
            service($vendor.'.jwt_revocation_registry'),
            param($vendor.'.block_jwts_on_revocation.user_claim'),
        ])
        ->tag('kernel.event_listener', [
            'event' => Events::JWT_DECODED,
        ]);
};
