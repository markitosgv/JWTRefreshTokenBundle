<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
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
        $config->setMetadataCache(new ArrayAdapter());
        $config->setQueryCache(new ArrayAdapter());
        $config->setResultCache(new ArrayAdapter());
        $config->setProxyDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files');
        $config->setProxyNamespace(__NAMESPACE__.'\Proxies');

        $driverChain = new MappingDriverChain();

        $driverChain->addDriver(
            new AttributeDriver([__DIR__.'/Fixtures/Entity']),
            'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Entity'
        );

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
