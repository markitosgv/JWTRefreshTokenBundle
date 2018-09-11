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
     * Process Doctrine mappings based on gesdinet_jwt_refresh_token.refresh_token_entity config parameter.
     * If this parameter contains user-defined entity, RefreshToken will be registered as a mapped superclass, not as an
     * entity, to prevent Doctrine creating table for it and avoid conflicts with user-defined entity.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $config = $container->getExtensionConfig('gesdinet_jwt_refresh_token')[0];

        $mappingPass = strtolower($config['manager_type']) === 'mongodb'
            ? $this->getODMCompilerPass($config)
            : $this->getORMCompilerPass($config);

        $mappingPass->process($container);
    }

    /**
     * @param array $config
     *
     * @return CompilerPassInterface
     */
    protected function getORMCompilerPass(array $config)
    {
        $nameSpace = 'Gesdinet\JWTRefreshTokenBundle\Entity';
        $mappings = array(
            realpath(dirname(__DIR__, 2) . '/Resources/config/orm/doctrine-orm') => $nameSpace,
        );

        if (isset($config['refresh_token_class']) || isset($config['refresh_token_entity'])) {
            $mappings[realpath(dirname(__DIR__, 2) . '/Resources/config/orm/doctrine-superclass')] = $nameSpace;
        } else {
            $mappings[realpath(dirname(__DIR__, 2) . '/Resources/config/orm/doctrine-entity')] = $nameSpace;
        }

        return DoctrineOrmMappingsPass::createYamlMappingDriver($mappings);
    }

    /**
     * @param array $config
     *
     * @return CompilerPassInterface
     */
    protected function getODMCompilerPass(array $config)
    {
        $nameSpace = 'Gesdinet\JWTRefreshTokenBundle\Document';
        $mappings = array(
            realpath(dirname(__DIR__, 2) . '/Resources/config/mongodb/doctrine-mongodb') => $nameSpace,
        );

        if (isset($config['refresh_token_class']) || isset($config['refresh_token_entity'])) {
            $mappings[realpath(dirname(__DIR__, 2) . '/Resources/config/mongodb/doctrine-superclass')] = $nameSpace;
        } else {
            $mappings[realpath(dirname(__DIR__, 2) . '/Resources/config/mongodb/doctrine-document')] = $nameSpace;
        }

        return DoctrineMongoDBMappingsPass::createYamlMappingDriver($mappings, array());
    }
}
