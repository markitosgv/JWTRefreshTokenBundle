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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class GesdinetJWTRefreshTokenExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(ExtractorInterface::class)->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $config['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $config['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $config['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $config['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.cookie', $config['cookie'] ?? []);
        $container->setParameter('gesdinet_jwt_refresh_token.logout_firewall_context', sprintf(
            'security.firewall.map.context.%s',
            $config['logout_firewall']
        ));
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration', $config['return_expiration']);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name', $config['return_expiration_parameter_name']);
        $container->setParameter('gesdinet.jwtrefreshtoken.refresh_token.class', $config['refresh_token_class']);

        if ($config['logout_firewall']) {
            $container->setDefinition('gesdinet_jwt_refresh_token.security.listener.logout.legacy_config', new ChildDefinition('gesdinet_jwt_refresh_token.security.listener.logout'))
                ->addArgument(new Parameter('gesdinet_jwt_refresh_token.logout_firewall_context'))
                ->addTag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onLogout']);
        }

        $objectManager = 'doctrine.orm.entity_manager';

        // Change the object manager to the MongoDB ODM if the configuration explicitly sets it or if the ORM is not installed and the MongoDB ODM is
        if ('mongodb' === strtolower($config['manager_type']) || (!class_exists(EntityManager::class) && class_exists(DocumentManager::class))) {
            $objectManager = 'doctrine_mongodb.odm.document_manager';
        }

        if (null !== $config['object_manager']) {
            $objectManager = $config['object_manager'];
        }

        $container->setAlias('gesdinet.jwtrefreshtoken.object_manager', $objectManager);
    }
}
