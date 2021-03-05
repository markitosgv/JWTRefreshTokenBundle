<?php

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class DoctrineMappingsCompilerPass.
 *
 * We can't add DoctrineOrmMappingsPass directly, because in GesdinetJWTRefreshTokenBundle->build we don't have current
 * bundle configuration yet.
 * This CompilerPass is effectively just a wrapper for DoctrineOrmMappingsPass, which passes mappings conditionally.
 */
final class DoctrineMappingsCompilerPass implements CompilerPassInterface
{
    /**
     * Process Doctrine mappings based on gesdinet_jwt_refresh_token.manager_type and
     * gesdinet_jwt_refresh_token.refresh_token_class config parameters.
     * Depending on the value of manager_type Doctrine's ORM or ODM mappings will be used.
     * If refresh_token_class parameter contains user-defined entity, RefreshToken will be registered as a mapped
     * superclass, not as an entity, to prevent Doctrine creating table for it and avoid conflicts with user-defined
     * entity.
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->getParameter('gesdinet_jwt_refresh_token.doctrine_mappings')) {
            return;
        }
        $config = $container->getExtensionConfig('gesdinet_jwt_refresh_token')[0];

        if (!class_exists('Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass')
            && (isset($config['manager_type']) && 'mongodb' === strtolower($config['manager_type']))
        ) {
            // skip MongoDB ODM mappings
            return;
        }

        if (!class_exists('Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass')
            && (!isset($config['manager_type']) || 'mongodb' !== strtolower($config['manager_type']))
        ) {
            // skip ORM mappings
            return;
        }

        $mappingPass = isset($config['manager_type']) && 'mongodb' === strtolower($config['manager_type'])
            ? $this->getODMCompilerPass($config)
            : $this->getORMCompilerPass($config);

        $mappingPass->process($container);
    }

    /**
     * @return CompilerPassInterface
     */
    private function getORMCompilerPass(array $config)
    {
        $nameSpace = 'Gesdinet\JWTRefreshTokenBundle\Entity';
        $mappings = [
            realpath(dirname(dirname(__DIR__)).'/Resources/config/orm/doctrine-orm') => $nameSpace,
        ];

        if (isset($config['refresh_token_class']) || isset($config['refresh_token_entity'])) {
            $mappings[realpath(dirname(dirname(__DIR__)).'/Resources/config/orm/doctrine-superclass')] = $nameSpace;
        } else {
            $mappings[realpath(dirname(dirname(__DIR__)).'/Resources/config/orm/doctrine-entity')] = $nameSpace;
        }

        return DoctrineOrmMappingsPass::createXmlMappingDriver($mappings);
    }

    /**
     * @return CompilerPassInterface
     */
    private function getODMCompilerPass(array $config)
    {
        $nameSpace = 'Gesdinet\JWTRefreshTokenBundle\Document';
        $mappings = [
            realpath(dirname(__DIR__, 2).'/Resources/config/mongodb/doctrine-mongodb') => $nameSpace,
        ];

        if (isset($config['refresh_token_class']) || isset($config['refresh_token_entity'])) {
            $mappings[realpath(dirname(dirname(__DIR__)).'/Resources/config/mongodb/doctrine-superclass')] = $nameSpace;
        } else {
            $mappings[realpath(dirname(dirname(__DIR__)).'/Resources/config/mongodb/doctrine-document')] = $nameSpace;
        }

        return DoctrineMongoDBMappingsPass::createXmlMappingDriver($mappings, []);
    }
}
