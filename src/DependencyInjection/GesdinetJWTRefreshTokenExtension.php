<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * @internal
 */
final class GesdinetJWTRefreshTokenExtension extends ConfigurableExtension
{
    /**
     * @param array<string, mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(ExtractorInterface::class)->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $mergedConfig['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $mergedConfig['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $mergedConfig['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $mergedConfig['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.cookie', $mergedConfig['cookie'] ?? []);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration', $mergedConfig['return_expiration']);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name', $mergedConfig['return_expiration_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.refresh_token.class', $mergedConfig['refresh_token_class']);
        $container->setParameter('gesdinet_jwt_refresh_token.default_invalid_batch_size', $mergedConfig['default_invalid_batch_size']);

        if (null !== $mergedConfig['dbal_connection']) {
            $this->configureDBALManager($container, $mergedConfig, $loader);
            $loader->load('dbal_services.php');
        } else {
            $this->configureObjectManager($container, $mergedConfig, $loader);
            $loader->load('om_services.php');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureDBALManager(ContainerBuilder $container, array $config, PhpFileLoader $loader): void
    {
        $container->setAlias('gesdinet_jwt_refresh_token.dbal.connection', $config['dbal_connection']);

        $container->setParameter('gesdinet_jwt_refresh_token.dbal.connection', $config['dbal_connection']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.table_name', $config['dbal_table_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.auto_create_table', $config['dbal_auto_create_table']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.columns', $config['dbal_columns']);
    }

    /**
     * @param array<string, mixed> $mergedConfig
     */
    private function configureObjectManager(ContainerBuilder $container, array $mergedConfig, PhpFileLoader $loader): void
    {
        if (null !== $mergedConfig['object_manager']) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', $mergedConfig['object_manager']);
        } elseif (ContainerBuilder::willBeAvailable('doctrine/orm', EntityManager::class, ['doctrine/doctrine-bundle'])) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine.orm.entity_manager');
        } elseif (ContainerBuilder::willBeAvailable('doctrine/mongodb-odm', DocumentManager::class, ['doctrine/mongodb-odm-bundle'])) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine_mongodb.odm.document_manager');
        }
    }
}
