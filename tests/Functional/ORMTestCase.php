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
    protected EntityManager $entityManager;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataCache(new ArrayAdapter());
        $config->setQueryCache(new ArrayAdapter());
        $config->setResultCache(new ArrayAdapter());

        // Native lazy objects, PHP 8.4 being the minimum now
        $config->enableNativeLazyObjects(true);

        $driverChain = new MappingDriverChain();

        $attributeDriver = new AttributeDriver([__DIR__.'/Fixtures/Entity']);

        $xmlDriver = new SimplifiedXmlDriver([(\dirname(__DIR__, 2).'/config/doctrine') => 'Gesdinet\\JWTRefreshTokenBundle\\Entity']);

        $driverChain->addDriver($attributeDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Entity');
        $driverChain->addDriver($xmlDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Entity');

        $config->setMetadataDriverImpl($driverChain);

        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $this->entityManager = new EntityManager(DriverManager::getConnection($conn, $config), $config);
    }
}
