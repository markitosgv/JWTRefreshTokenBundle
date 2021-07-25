<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class ORMTestCase extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected function setUp(): void
    {
        $config = new Configuration();

        if (method_exists($config, 'setMetadataCache')) {
            $config->setMetadataCache(new ArrayAdapter());
        } else {
            $config->setMetadataCacheImpl(DoctrineProvider::wrap(new ArrayAdapter()));
        }

        $config->setQueryCacheImpl(DoctrineProvider::wrap(new ArrayAdapter()));
        $config->setResultCacheImpl(DoctrineProvider::wrap(new ArrayAdapter()));
        $config->setProxyDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files');
        $config->setProxyNamespace(__NAMESPACE__.'\Proxies');

        $driverChain = new MappingDriverChain();

        $annotationDriver = $config->newDefaultAnnotationDriver([__DIR__.'/Fixtures/Entity'], false);

        $xmlDriver = new SimplifiedXmlDriver([(\dirname(__DIR__, 2).'/Resources/config/doctrine') => 'Gesdinet\\JWTRefreshTokenBundle\\Entity']);

        $driverChain->addDriver($annotationDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Entity');
        $driverChain->addDriver($xmlDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Entity');

        $config->setMetadataDriverImpl($driverChain);

        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $this->entityManager = EntityManager::create($conn, $config);
    }
}
