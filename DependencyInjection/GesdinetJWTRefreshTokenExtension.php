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
use Symfony\Component\DependencyInjection\Exception\RuntimeException;;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class GesdinetJWTRefreshTokenExtension extends ConfigurableExtension
{
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(ExtractorInterface::class)->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $mergedConfig['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $mergedConfig['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $mergedConfig['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $mergedConfig['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.cookie', $mergedConfig['cookie'] ?? []);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration', $mergedConfig['return_expiration']);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name', $mergedConfig['return_expiration_parameter_name']);
        $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', $mergedConfig['refresh_token_class']);

        /*
         * Configuration preference:
         * - Explicitly configured "object_manager" node
         * - Feature detection (ORM then MongoDB ODM)
         */
        if (null !== $mergedConfig['object_manager']) {
            $objectManager = $mergedConfig['object_manager'];
        } elseif (ContainerBuilder::willBeAvailable('doctrine/orm', EntityManager::class, ['doctrine/doctrine-bundle'])) {
            $objectManager = 'doctrine.orm.entity_manager';
        } elseif (ContainerBuilder::willBeAvailable('doctrine/mongodb-odm', DocumentManager::class, ['doctrine/mongodb-odm-bundle'])) {
            $objectManager = 'doctrine_mongodb.odm.document_manager';
        } else {
            throw new RuntimeException('The "object_manager" node must be configured when neither "doctrine/orm" or "doctrine/mongodb-odm" are installed.');
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.object_manager', $objectManager);
    }
}
