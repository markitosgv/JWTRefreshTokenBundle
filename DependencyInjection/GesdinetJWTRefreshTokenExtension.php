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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class GesdinetJWTRefreshTokenExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $config['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $config['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.security.firewall', $config['firewall']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_provider', $config['user_provider']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_identity_field', $config['user_identity_field']);

        $refreshTokenClass = 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken';
        $objectManager = 'doctrine.orm.entity_manager';

        if (strtolower($config['manager_type']) === 'mongodb') {
            $refreshTokenClass = 'Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken';
            $objectManager = 'doctrine_mongodb.odm.document_manager';
        }

        if ($this->getRefreshTokenClass($config) !== null) {
            $refreshTokenClass = $this->getRefreshTokenClass($config);
        }

        if ($this->getObjectManager($config) !== null) {
            $objectManager = $this->getObjectManager($config);
        }

        $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', $refreshTokenClass);
        $container->setParameter('gesdinet.jwtrefreshtoken.object_manager.id', $objectManager);
    }

    /**
     * Get refresh token class from configuration.
     *
     * Supports deprecated configuration
     *
     * @param array $config
     *
     * @return string|null
     */
    protected function getRefreshTokenClass(array $config)
    {
        if (isset($config['refresh_token_class'])) {
            return $config['refresh_token_class'];
        }

        return $config['refresh_token_entity'] ?: null;
    }

    /**
     * Get object manager from configuration.
     *
     * Supports deprecated configuration
     *
     * @param array $config
     *
     * @return string|null
     */
    protected function getObjectManager(array $config)
    {
        if (isset($config['object_manager'])) {
            return $config['object_manager'];
        }

        return $objectManager = $config['entity_manager'] ?: null;
    }
}
