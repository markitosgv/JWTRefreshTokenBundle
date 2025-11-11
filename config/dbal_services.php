<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\EnsureTableExistsListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    //
    // DBAL Table Schema Manager
    //
    $services->set('gesdinet_jwt_refresh_token.dbal.table_schema_manager')
        ->class(TableSchemaManager::class)
        ->public(false)
        ->args([
            service('doctrine.dbal.default_connection'),
            param('gesdinet_jwt_refresh_token.dbal.connection'),
            param('gesdinet_jwt_refresh_token.dbal.table_name'),
            param('gesdinet_jwt_refresh_token.dbal.columns'),
        ]);

    $services->alias(TableSchemaManager::class, 'gesdinet_jwt_refresh_token.dbal.table_schema_manager');

    //
    // DBAL Refresh Token Manager
    //
    $services->set('gesdinet_jwt_refresh_token.refresh_token_manager')
        ->class(RefreshTokenManager::class)
        ->public(true)
        ->args([
            service('doctrine.dbal.default_connection'),
            param('gesdinet_jwt_refresh_token.default_invalid_batch_size'),
            param('gesdinet_jwt_refresh_token.dbal.table_name'),
            param('gesdinet_jwt_refresh_token.refresh_token.class'),
            param('gesdinet_jwt_refresh_token.dbal.columns'),
        ]);

    $services->alias(RefreshTokenManagerInterface::class, 'gesdinet_jwt_refresh_token.refresh_token_manager');

    //
    // EnsureTableExistsListener
    //
    $services->set('gesdinet_jwt_refresh_token.ensure_table_exists_listener')
        ->class(EnsureTableExistsListener::class)
        ->public(true)
        ->args([
            service('gesdinet_jwt_refresh_token.dbal.table_schema_manager'),
            param('gesdinet_jwt_refresh_token.dbal.auto_create_table'),
            param('kernel.cache_dir'),
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber');
};
