<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use PHPUnit\Framework\TestCase;

class TableSchemaManagerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testCreateTableWithDefaultConfiguration(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $this->assertFalse($manager->tableExists(), 'Table should not exist initially');

        $manager->createTable();

        $this->assertTrue($manager->tableExists(), 'Table should exist after creation');

        // Verify table structure
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('refresh_tokens');

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('refresh_token', $columns);
        $this->assertArrayHasKey('username', $columns);
        $this->assertArrayHasKey('valid', $columns);

        // Verify column properties
        $this->assertTrue($columns['id']->getAutoincrement(), 'ID should be autoincrement');
        $this->assertTrue($columns['id']->getNotnull(), 'ID should be not null');
        $this->assertSame(255, $columns['refresh_token']->getLength(), 'refresh_token should have length 255');
        $this->assertTrue($columns['refresh_token']->getNotnull(), 'refresh_token should be not null');
        $this->assertSame(255, $columns['username']->getLength(), 'username should have length 255');
        $this->assertTrue($columns['username']->getNotnull(), 'username should be not null');

        // Verify indexes
        $indexes = $schemaManager->listTableIndexes('refresh_tokens');

        $this->assertArrayHasKey('primary', $indexes, 'Primary key should exist');
        $this->assertArrayHasKey('uniq_refresh_token', $indexes, 'Unique index on refresh_token should exist');
        $this->assertArrayHasKey('idx_username', $indexes, 'Index on username should exist');
        $this->assertArrayHasKey('idx_valid', $indexes, 'Index on valid should exist');

        $this->assertTrue($indexes['uniq_refresh_token']->isUnique(), 'refresh_token index should be unique');
        $this->assertFalse($indexes['idx_username']->isUnique(), 'username index should not be unique');
    }

    public function testCreateTableWithCustomTableName(): void
    {
        $manager = new TableSchemaManager($this->connection, 'custom_tokens', []);

        $manager->createTable();

        $this->assertTrue($manager->tableExists());

        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        $this->assertContains('custom_tokens', $tables);
        $this->assertNotContains('refresh_tokens', $tables);
    }

    public function testCreateTableWithCustomColumnNames(): void
    {
        $customConfig = [
            'id' => [
                'name' => 'token_id',
                'type' => Types::INTEGER,
            ],
            'refreshToken' => [
                'name' => 'token_hash',
                'type' => Types::STRING,
            ],
            'username' => [
                'name' => 'user_identifier',
                'type' => Types::STRING,
            ],
            'valid' => [
                'name' => 'expires_at',
                'type' => Types::DATETIME_MUTABLE,
            ],
        ];

        $manager = new TableSchemaManager($this->connection, 'custom_tokens', $customConfig);

        $manager->createTable();

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('custom_tokens');

        $this->assertArrayHasKey('token_id', $columns);
        $this->assertArrayHasKey('token_hash', $columns);
        $this->assertArrayHasKey('user_identifier', $columns);
        $this->assertArrayHasKey('expires_at', $columns);

        // Verify old names don't exist
        $this->assertArrayNotHasKey('id', $columns);
        $this->assertArrayNotHasKey('refresh_token', $columns);
        $this->assertArrayNotHasKey('username', $columns);
        $this->assertArrayNotHasKey('valid', $columns);
    }

    public function testDropTable(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $manager->createTable();
        $this->assertTrue($manager->tableExists());

        $manager->dropTable();
        $this->assertFalse($manager->tableExists(), 'Table should not exist after drop');
    }

    public function testDropTableWhenTableDoesNotExist(): void
    {
        $manager = new TableSchemaManager($this->connection, 'nonexistent_table', []);

        $this->assertFalse($manager->tableExists());

        // Should not throw exception
        $manager->dropTable();

        $this->assertFalse($manager->tableExists());
    }

    public function testTableExists(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $this->assertFalse($manager->tableExists(), 'Should return false when table does not exist');

        $manager->createTable();

        $this->assertTrue($manager->tableExists(), 'Should return true when table exists');

        $manager->dropTable();

        $this->assertFalse($manager->tableExists(), 'Should return false after table is dropped');
    }

    public function testCreateTableIfNotExists(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $this->assertFalse($manager->tableExists());

        $manager->createTableIfNotExists();

        $this->assertTrue($manager->tableExists());

        // Calling again should not throw exception
        $manager->createTableIfNotExists();

        $this->assertTrue($manager->tableExists());
    }

    public function testCreateTableWithDropIfExists(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        // Create initial table
        $manager->createTable();
        $this->assertTrue($manager->tableExists());

        // Insert a row to verify table is dropped and recreated
        $this->connection->insert('refresh_tokens', [
            'refresh_token' => 'test-token',
            'username' => 'testuser',
            'valid' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ]);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens');
        $this->assertEquals(1, $count);

        // Recreate with dropIfExists
        $manager->createTable(true);

        $this->assertTrue($manager->tableExists());

        // Verify table was dropped and recreated (no rows)
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens');
        $this->assertEquals(0, $count, 'Table should be empty after drop and recreate');
    }

    public function testCreateTableTwiceWithoutDropIfExistsThrowsException(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $manager->createTable();

        $this->expectException(Exception::class);

        // Attempting to create again without dropIfExists should throw exception
        $manager->createTable(false);
    }

    public function testGetDefaultColumnConfig(): void
    {
        $config = TableSchemaManager::getDefaultColumnConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('id', $config);
        $this->assertArrayHasKey('refreshToken', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('valid', $config);

        $this->assertSame('id', $config['id']['name']);
        $this->assertSame(Types::INTEGER, $config['id']['type']);

        $this->assertSame('refresh_token', $config['refreshToken']['name']);
        $this->assertSame(Types::STRING, $config['refreshToken']['type']);

        $this->assertSame('username', $config['username']['name']);
        $this->assertSame(Types::STRING, $config['username']['type']);

        $this->assertSame('valid', $config['valid']['name']);
        $this->assertSame(Types::DATETIME_MUTABLE, $config['valid']['type']);
    }

    public function testEmptyColumnConfigUsesDefaults(): void
    {
        // Test with empty config
        $managerWithEmpty = new TableSchemaManager($this->connection, 'tokens_empty', []);
        $managerWithEmpty->createTable();

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('tokens_empty');

        $defaultConfig = TableSchemaManager::getDefaultColumnConfig();

        // Verify all default columns are present
        foreach ($defaultConfig as $alias => $config) {
            $this->assertArrayHasKey($config['name'], $columns, "Column {$config['name']} should exist");
        }
    }

    public function testPartialColumnConfigMergesWithMissingAliases(): void
    {
        // Only provide custom config for id and refreshToken
        $partialConfig = [
            'id' => [
                'name' => 'custom_id',
                'type' => Types::INTEGER,
            ],
            'refreshToken' => [
                'name' => 'custom_token',
                'type' => Types::STRING,
            ],
        ];

        $manager = new TableSchemaManager($this->connection, 'partial_table', $partialConfig);

        $manager->createTable();

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('partial_table');

        // Should only have the configured columns
        $this->assertArrayHasKey('custom_id', $columns);
        $this->assertArrayHasKey('custom_token', $columns);

        // Should not have username and valid since they weren't in config
        $this->assertArrayNotHasKey('username', $columns);
        $this->assertArrayNotHasKey('valid', $columns);

        // Verify indexes
        $indexes = $schemaManager->listTableIndexes('partial_table');

        // Should have indexes for configured columns
        $this->assertArrayHasKey('primary', $indexes);
        $this->assertArrayHasKey('uniq_refresh_token', $indexes);

        // Should not have indexes for missing columns
        $this->assertArrayNotHasKey('idx_username', $indexes);
        $this->assertArrayNotHasKey('idx_valid', $indexes);
    }

    public function testCustomColumnTypesAreRespected(): void
    {
        $customConfig = [
            'id' => [
                'name' => 'id',
                'type' => Types::BIGINT, // Different from default INTEGER
            ],
            'refreshToken' => [
                'name' => 'refresh_token',
                'type' => Types::TEXT, // Different from default STRING
            ],
            'username' => [
                'name' => 'username',
                'type' => Types::STRING,
            ],
            'valid' => [
                'name' => 'valid',
                'type' => Types::DATETIME_IMMUTABLE, // Different from default DATETIME_MUTABLE
            ],
        ];

        $manager = new TableSchemaManager($this->connection, 'custom_types', $customConfig);

        $manager->createTable();

        // Verify table was created successfully by inserting and retrieving data
        $validTime = new \DateTimeImmutable('+1 hour');
        $this->connection->insert('custom_types', [
            'refresh_token' => 'test-token',
            'username' => 'testuser',
            'valid' => $validTime->format('Y-m-d H:i:s'),
        ]);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM custom_types WHERE refresh_token = ?',
            ['test-token']
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('refresh_token', $result);

        // Verify TEXT column can hold larger data than VARCHAR(255)
        $largeText = str_repeat('a', 1000);
        $this->connection->insert('custom_types', [
            'refresh_token' => $largeText,
            'username' => 'user2',
            'valid' => $validTime->format('Y-m-d H:i:s'),
        ]);

        $result = $this->connection->fetchAssociative(
            'SELECT refresh_token FROM custom_types WHERE username = ?',
            ['user2']
        );

        $this->assertSame($largeText, $result['refresh_token'], 'TEXT type should support large strings');
    }

    public function testTableCanBeUsedForInsertAndSelect(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $manager->createTable();

        // Insert test data
        $validTime = new \DateTime('+1 hour');
        $this->connection->insert('refresh_tokens', [
            'refresh_token' => 'test-token-123',
            'username' => 'testuser',
            'valid' => $validTime->format('Y-m-d H:i:s'),
        ]);

        // Verify data can be retrieved
        $result = $this->connection->fetchAssociative(
            'SELECT * FROM refresh_tokens WHERE refresh_token = ?',
            ['test-token-123']
        );

        $this->assertIsArray($result);
        $this->assertSame('test-token-123', $result['refresh_token']);
        $this->assertSame('testuser', $result['username']);
    }

    public function testUniqueConstraintOnRefreshToken(): void
    {
        $manager = new TableSchemaManager($this->connection, 'refresh_tokens', []);

        $manager->createTable();

        // Insert first token
        $this->connection->insert('refresh_tokens', [
            'refresh_token' => 'duplicate-token',
            'username' => 'user1',
            'valid' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ]);

        // Attempting to insert duplicate refresh_token should fail
        $this->expectException(Exception::class);

        $this->connection->insert('refresh_tokens', [
            'refresh_token' => 'duplicate-token',
            'username' => 'user2', // Different username but same token
            'valid' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ]);
    }
}
