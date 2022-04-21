<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use MongoDB\Client;
use MongoDB\Model\DatabaseInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class ODMTestCase extends TestCase
{
    /**
     * @var DocumentManager
     */
    protected $documentManager;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataCache(new ArrayAdapter());
        $config->setProxyDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files/Proxies');
        $config->setProxyNamespace(__NAMESPACE__.'\Proxies');
        $config->setHydratorDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files/Hydrators');
        $config->setHydratorNamespace(__NAMESPACE__.'\Hydrators');
        $config->setPersistentCollectionDir(sys_get_temp_dir().'/JWTRefreshTokenBundle/_files/PersistentCollections');
        $config->setPersistentCollectionNamespace(__NAMESPACE__.'\PersistentCollections');
        $config->setDefaultDB(JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE);

        $driverChain = new MappingDriverChain();

        $attributeDriver = new AttributeDriver([__DIR__.'/Fixtures/Document']);

        $xmlDriver = new SimplifiedXmlDriver(
            [(\dirname(__DIR__, 2).'/Resources/config/doctrine') => 'Gesdinet\\JWTRefreshTokenBundle\\Document'],
            '.mongodb.xml'
        );

        $driverChain->addDriver($attributeDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Tests\\Functional\\Fixtures\\Document');
        $driverChain->addDriver($xmlDriver, 'Gesdinet\\JWTRefreshTokenBundle\\Document');

        $config->setMetadataDriverImpl($driverChain);

        $client = new Client(
            getenv('JWTREFRESHTOKENBUNDLE_MONGODB_SERVER') ?: JWTREFRESHTOKENBUNDLE_MONGODB_SERVER,
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array']]
        );

        $this->documentManager = DocumentManager::create($client, $config);
    }

    /**
     * Based on `Doctrine\ODM\MongoDB\Tests\BaseTest::tearDown()`.
     */
    protected function tearDown(): void
    {
        if (!$this->documentManager) {
            return;
        }

        $client = $this->documentManager->getClient();
        $databaseNames = array_map(
            static function (DatabaseInfo $database): string {
                return $database->getName();
            },
            iterator_to_array($client->listDatabases())
        );

        if (!in_array(JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE, $databaseNames)) {
            return;
        }

        $collections = $client->selectDatabase(JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE)->listCollections();

        foreach ($collections as $collection) {
            if (preg_match('#^system\.#', $collection->getName())) {
                continue;
            }

            $client->selectCollection(JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE, $collection->getName())->drop();
        }
    }
}
