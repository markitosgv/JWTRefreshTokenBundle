<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\EventListener\BlockPreviousJWTListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    $services->set($vendor.'.event_listener.block_previous_jwt')
        ->class(BlockPreviousJWTListener::class)
        ->args([
            service('lexik_jwt_authentication.blocked_token_manager'),
            service('lexik_jwt_authentication.extractor.chain_extractor'),
            service('lexik_jwt_authentication.jwt_manager'),
            service('request_stack'),
        ])
        ->tag('kernel.event_listener', ['event' => 'gesdinet.refresh_token']);
};
