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
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
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
     * @var array<string, array{name: string, type: string}>
     */
    private array $columnConfig;

    /**
     * @param Connection                                       $connection   DBAL connection
     * @param string                                           $tableName    Name of the refresh tokens table
     * @param array<string, array{name: string, type: string}> $columnConfig Column configuration map
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private Connection $connection,
        private string $tableName,
        array $columnConfig
    ) {
        $this->columnConfig = !empty($columnConfig) ? $columnConfig : self::getDefaultColumnConfig();
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

            if ('id' === $alias) {
                $column->setAutoincrement(true)->setNotnull(true);
            } elseif ('refreshToken' === $alias) {
                $column->setLength(255)->setNotnull(true);
            } elseif ('username' === $alias) {
                $column->setLength(255)->setNotnull(true);
            } elseif ('valid' === $alias) {
                $column->setNotnull(true);
            } elseif ('family' === $alias) {
                // Nullable because a token stored before the column existed has no chain to name,
                // and because a token class of your own need not have families at all
                $column->setLength(32)->setNotnull(false);
            } elseif ('familyValid' === $alias) {
                // Nullable for the same reasons, and because a chain has no ceiling unless
                // max_session_lifetime puts one on it
                $column->setNotnull(false);
            }
        }

        if (isset($this->columnConfig['id']) && '' !== $this->columnConfig['id']['name']) {
            $this->addPrimaryKey($table, $this->columnConfig['id']['name']);
        }

        if (isset($this->columnConfig['refreshToken'])) {
            $table->addUniqueIndex([$this->columnConfig['refreshToken']['name']], $this->indexName('UNIQ_REFRESH_TOKEN'));
        }

        if (isset($this->columnConfig['username'])) {
            $table->addIndex([$this->columnConfig['username']['name']], $this->indexName('IDX_USERNAME'));
        }

        if (isset($this->columnConfig['valid'])) {
            $table->addIndex([$this->columnConfig['valid']['name']], $this->indexName('IDX_VALID'));
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
     *
     * @psalm-pure
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
            'family' => [
                'name' => 'family',
                'type' => Types::STRING,
            ],
            'familyValid' => [
                'name' => 'family_valid',
                'type' => Types::DATETIME_MUTABLE,
            ],
        ];
    }

    /**
     * Builds an index name unique to this table.
     *
     * Index names are scoped to the schema on PostgreSQL and to the whole database on SQLite, not
     * to the table as they are on MySQL. A name fixed in the source therefore collides the moment
     * an application has the bundle manage a second table: the second one fails to create, and the
     * error names an index rather than anything identifying this bundle. Deriving the name from the
     * table makes it unique for the same reason the table name is.
     *
     * @param non-empty-string $purpose
     *
     * @return non-empty-string
     *
     * @psalm-mutation-free
     */
    private function indexName(string $purpose): string
    {
        $name = $purpose.'_'.strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $this->tableName));

        // PostgreSQL truncates identifiers at 63 characters, which would fold two long table names
        // back into one index name and reintroduce the collision. A hash of the full table name
        // stays distinct where a truncated prefix does not.
        if (\strlen($name) > 63) {
            return $purpose.'_'.strtoupper(dechex(crc32($this->tableName)));
        }

        return $name;
    }

    /**
     * Marks the column as the primary key.
     *
     * @param non-empty-string $columnName
     */
    private function addPrimaryKey(Table $table, string $columnName): void
    {
        $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [UnqualifiedName::unquoted($columnName)], false));
    }
}
