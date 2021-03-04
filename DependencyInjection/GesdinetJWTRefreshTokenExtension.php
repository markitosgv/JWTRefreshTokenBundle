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

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $config['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $config['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.security.firewall', $config['firewall']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_provider', $config['user_provider']);
        $container->setParameter('gesdinet_jwt_refresh_token.user_identity_field', $config['user_identity_field']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $config['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $config['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.doctrine_mappings', $config['doctrine_mappings']);

        $refreshTokenClass = 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken';
        $objectManager = 'doctrine.orm.entity_manager';

        if (!class_exists('Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass')
            || 'mongodb' === strtolower($config['manager_type'])
        ) {
            $refreshTokenClass = 'Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken';
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
     * Get refresh token class from configuration.
     *
     * Supports deprecated configuration
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
     * @return string|null
     */
    protected function getObjectManager(array $config)
    {
        if (isset($config['object_manager'])) {
            return $config['object_manager'];
        }

        return $config['entity_manager'] ?: null;
    }
}
