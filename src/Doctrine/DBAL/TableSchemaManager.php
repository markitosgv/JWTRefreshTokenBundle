<?php

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Types;

/**
 * Manages the database schema for refresh tokens when using DBAL.
 *
 * This service is responsible for creating and managing the refresh tokens table.
 * Similar to Doctrine Migrations or Symfony Messenger's transport table auto-creation.
 */
final readonly class TableSchemaManager
{
    /**
     * @var array[]
     */
    private array $columnConfig;

    /**
     * @param Connection                                       $connection   DBAL connection
     * @param string                                           $tableName    Name of the refresh tokens table
     * @param array<string, array{name: string, type: string}> $columnConfig Column configuration map
     */
    public function __construct(
        private Connection $connection,
        private string $tableName,
        array $columnConfig
    ) {
        $this->columnConfig = !empty($columnConfig) ? $columnConfig : $this->getDefaultColumnConfig();
    }

    /**
     * Creates the refresh token table if it doesn't exist.
     *
     * @throws Exception
     * @throws TypesException
     */
    public function createTableIfNotExists(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist([$this->tableName])) {
            return;
        }

        $this->createTable();
    }

    /**
     * Creates the refresh token table based on the column configuration.
     *
     * @param bool $dropIfExists Whether to drop the table if it already exists
     *
     * @throws Exception
     * @throws TypesException
     */
    public function createTable(bool $dropIfExists = false): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schema = new Schema();

        if ($dropIfExists && $schemaManager->tablesExist([$this->tableName])) {
            $schemaManager->dropTable($this->tableName);
        }

        $table = $schema->createTable($this->tableName);

        foreach ($this->columnConfig as $alias => $config) {
            $columnName = $config['name'];
            $columnType = $config['type'];

            $column = $table->addColumn($columnName, $columnType);

            match ($alias) {
                'id' => $column->setAutoincrement(true)->setNotnull(true),
                'refreshToken' => $column->setLength(255)->setNotnull(true),
                'username' => $column->setLength(255)->setNotnull(true),
                'valid' => $column->setNotnull(true),
                default => $column
            };
        }

        if (isset($this->columnConfig['id'])) {
            $table->setPrimaryKey([$this->columnConfig['id']['name']]);
        }

        if (isset($this->columnConfig['refreshToken'])) {
            $table->addUniqueIndex([$this->columnConfig['refreshToken']['name']], 'UNIQ_REFRESH_TOKEN');
        }

        if (isset($this->columnConfig['username'])) {
            $table->addIndex([$this->columnConfig['username']['name']], 'IDX_USERNAME');
        }

        if (isset($this->columnConfig['valid'])) {
            $table->addIndex([$this->columnConfig['valid']['name']], 'IDX_VALID');
        }

        $queries = $schema->toSql($this->connection->getDatabasePlatform());
        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
    }

    /**
     * Drops the refresh token table if it exists.
     *
     * @throws Exception
     */
    public function dropTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist([$this->tableName])) {
            $schemaManager->dropTable($this->tableName);
        }
    }

    /**
     * Checks if the refresh token table exists.
     *
     * @throws Exception
     */
    public function tableExists(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist([$this->tableName]);
    }

    /**
     * Returns the default column configuration for refresh tokens.
     *
     * @return array<string, array{name: string, type: string}>
     */
    public static function getDefaultColumnConfig(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Types::INTEGER,
            ],
            'refreshToken' => [
                'name' => 'refresh_token',
                'type' => Types::STRING,
            ],
            'username' => [
                'name' => 'username',
                'type' => Types::STRING,
            ],
            'valid' => [
                'name' => 'valid',
                'type' => Types::DATETIME_MUTABLE,
            ],
        ];
    }
}
