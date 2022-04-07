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
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as RefreshTokenDocument;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as RefreshTokenEntity;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class GesdinetJWTRefreshTokenExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(ExtractorInterface::class)->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $config['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $config['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.security.firewall', $config['firewall']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_provider', $config['user_provider']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_identity_field', $config['user_identity_field']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $config['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $config['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.doctrine_mappings', $config['doctrine_mappings']);
        $container->setParameter('gesdinet_jwt_refresh_token.cookie', $config['cookie'] ?? []);
        $container->setParameter('gesdinet_jwt_refresh_token.logout_firewall_context', sprintf(
            'security.firewall.map.context.%s',
            $config['logout_firewall']
        ));
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration', $config['return_expiration']);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name', $config['return_expiration_parameter_name']);

        $refreshTokenClass = RefreshTokenEntity::class;
        $objectManager = 'doctrine.orm.entity_manager';

        // Change the refresh token and object manager to the MongoDB ODM if the configuration explicitly sets it or if the ORM is not installed and the MongoDB ODM is
        if ('mongodb' === strtolower($config['manager_type']) || (!class_exists(EntityManager::class) && class_exists(DocumentManager::class))) {
            $refreshTokenClass = RefreshTokenDocument::class;
            $objectManager = 'doctrine_mongodb.odm.document_manager';
        }

        if (null !== $this->getRefreshTokenClass($config)) {
            $refreshTokenClass = $this->getRefreshTokenClass($config);
        }

        if (null !== $this->getObjectManager($config)) {
            $objectManager = $this->getObjectManager($config);
        }

        $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', $refreshTokenClass);
        $container->setParameter('gesdinet.jwtrefreshtoken.object_manager.id', $objectManager);
        $container->setParameter('gesdinet.jwtrefreshtoken.user_checker.id', $config['user_checker']);
    }

    /**
     * Get the refresh token class from configuration.
     *
     * Falls back to deprecated configuration nodes if necessary.
     */
    protected function getRefreshTokenClass(array $config): ?string
    {
        if (isset($config['refresh_token_class'])) {
            return $config['refresh_token_class'];
        }

        return $config['refresh_token_entity'] ?: null;
    }

    /**
     * Get object manager from configuration.
     *
     * Falls back to deprecated configuration nodes if necessary.
     */
    protected function getObjectManager(array $config): ?string
    {
        if (isset($config['object_manager'])) {
            return $config['object_manager'];
        }

        return $config['entity_manager'] ?: null;
    }
}
