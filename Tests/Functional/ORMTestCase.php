<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
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

        if (method_exists($config, 'setQueryCache')) {
            $config->setQueryCache(new ArrayAdapter());
        } else {
            $config->setQueryCacheImpl(DoctrineProvider::wrap(new ArrayAdapter()));
        }

        if (method_exists($config, 'setResultCache')) {
            $config->setResultCache(new ArrayAdapter());
        } else {
            $config->setResultCacheImpl(DoctrineProvider::wrap(new ArrayAdapter()));
        }

        $config->setProxyDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files');
        $config->setProxyNamespace(__NAMESPACE__.'\Proxies');

        $driverChain = new MappingDriverChain();

        if (\PHP_VERSION_ID >= 80000 && class_exists(AttributeDriver::class)) {
            $driverChain->addDriver(
                new AttributeDriver([__DIR__.'/Fixtures/Entity']),
                'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Entity'
            );
        } elseif (class_exists(AnnotationDriver::class) && interface_exists(Reader::class)) {
            $driverChain->addDriver(
                new AnnotationDriver(new AnnotationReader(), [__DIR__.'/Fixtures/Entity']),
                'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Entity'
            );
        }

        $driverChain->addDriver(
            new SimplifiedXmlDriver([(\dirname(__DIR__, 2).'/Resources/config/doctrine') => 'Gesdinet\\JWTRefreshTokenBundle\\Entity']),
            'Gesdinet\\JWTRefreshTokenBundle\\Entity'
        );

        $config->setMetadataDriverImpl($driverChain);

        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        if (method_exists(EntityManager::class, 'create')) {
            $this->entityManager = EntityManager::create($conn, $config);
        } else {
            $this->entityManager = new EntityManager(DriverManager::getConnection($conn, $config), $config);
        }
    }
}
