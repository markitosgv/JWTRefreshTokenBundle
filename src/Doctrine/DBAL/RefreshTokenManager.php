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

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Types;
use Generator;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Throwable;

final readonly class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * @var array<string, array{name: string, type: string}>
     */
    private array $columnConfig;

    /**
     * @param positive-int $defaultBatchSize
     * @param Connection $connection DBAL connection
     * @param string $tableName Name of the refresh tokens table
     * @param class-string<RefreshTokenInterface> $class Fully qualified class name for refresh token instances
     * @param array<string, array{name: string, type: string}> $columnConfig Map of aliases to column configuration ['alias' => ['name' => 'column_name', 'type' => Types::STRING]]
     */
    public function __construct(
        private Connection $connection,
        private int        $defaultBatchSize,
        private string     $tableName,
        private string     $class,
        array              $columnConfig = []
    )
    {
        $this->columnConfig = $columnConfig ?: $this->getDefaultColumnConfig();
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
     * Returns the default column configuration.
     *
     * @return array<string, array{name: string, type: string}>
     */
    private function getDefaultColumnConfig(): array
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

    /**
     * Get column name by alias.
     */
    private function getColumnName(string $alias): string
    {
        return $this->columnConfig[$alias]['name'] ?? $alias;
    }

    /**
     * Hydrates a RefreshToken from raw database data using Closure::bind.
     *
     * @param array<string, mixed> $data Raw data from database
     */
    private function hydrate(array $data): RefreshTokenInterface
    {
        $class = $this->class;
        $instance = new $class();

        $columnConfig = $this->columnConfig;
        $conn = $this->connection;

        /**
         * @param AbstractRefreshToken $object
         * @param array<string, mixed> $data
         */
        $hydrator = \Closure::bind(function ($object, array $data) use ($columnConfig, $conn): void {
            $object->id = $data[$columnConfig['id']['name']] ?? $data['id'] ?? null;
            $object->refreshToken = $data[$columnConfig['refreshToken']['name']] ?? $data['refresh_token'] ?? null;
            $object->username = $data[$columnConfig['username']['name']] ?? $data['username'] ?? null;
            $object->valid = $conn->convertToPHPValue($data[$columnConfig['valid']['name']] ?? $data['valid'] ?? null, $columnConfig['valid']['type'] ?? 'datetime');
        }, null, $class);

        $hydrator($instance, $data);

        return $instance;
    }

    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        $qb = $this->query()
            ->where($this->getColumnName('refreshToken') . ' = :refreshToken')
            ->setParameter('refreshToken', $refreshToken)
            ->setMaxResults(1);

        $data = $qb->fetchAssociative();
        if (false === $data) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        $qb = $this->query()
            ->where($this->getColumnName('username') . ' = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->orderBy($this->getColumnName('valid'), 'DESC');

        $data = $qb->fetchAssociative();
        if (false === $data) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $token = $this->get($refreshToken->getRefreshToken());

        if (!$token instanceof RefreshTokenInterface) {
            $this->connection->createQueryBuilder()
                ->insert($this->tableName)
                ->values([
                    $this->getColumnName('refreshToken') => ':refresh_token',
                    $this->getColumnName('username') => ':username',
                    $this->getColumnName('valid') => ':valid',
                ])
                ->setParameters([
                    'refresh_token' => $refreshToken->getRefreshToken(),
                    'username' => $refreshToken->getUsername(),
                    'valid' => $refreshToken->getValid(),
                ], [
                    'valid' => 'datetime',
                ])
                ->executeStatement();
        } else {
            $this->connection->createQueryBuilder()
                ->update($this->tableName)
                ->set($this->getColumnName('username'), ':username')
                ->set($this->getColumnName('valid'), ':valid')
                ->where($this->getColumnName('refreshToken') . ' = :refresh_token')
                ->setParameters([
                    'refresh_token' => $refreshToken->getRefreshToken(),
                    'username' => $refreshToken->getUsername(),
                    'valid' => $refreshToken->getValid(),
                ], [
                    'valid' => 'datetime',
                ])
                ->executeStatement();
        }
    }

    /**
     * Deletes the given refresh token and returns the number of rows affected.
     *
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     *
     * @throws Exception
     */
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        return $this->connection->delete(
            $this->tableName,
            [$this->getColumnName('refreshToken') => $refreshToken->getRefreshToken()]
        );
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * @param ?DateTimeInterface $datetime The date and time to consider for invalidation
     * @param ?positive-int $batchSize Number of tokens to process per batch, defaults to the {@see $defaultBatchSize} property when not provided
     * @param int $offset The offset to start processing from, defaults to 0
     * @param bool $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|Throwable
     */
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        $batchSize ??= $this->defaultBatchSize;
        $datetime ??= new DateTime();
        $allRevokedData = [];

        $this->connection->beginTransaction();
        try {
            foreach ($this->generateInvalidTokenBatches($datetime, $batchSize, $offset) as $batchData) {
                $ids = array_column($batchData, $this->getColumnName('id'));

                $this->connection->executeStatement(
                    sprintf(
                        'DELETE FROM %s WHERE %s IN (%s)',
                        $this->tableName,
                        $this->getColumnName('id'),
                        implode(',', array_fill(0, count($ids), '?'))
                    ),
                    $ids
                );

                $allRevokedData = array_merge($allRevokedData, $batchData);
            }

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return array_map(fn(array $data) => $this->hydrate($data), $allRevokedData);
    }

    /**
     * Revokes all invalid (expired) refresh tokens.
     *
     * @param ?DateTimeInterface $datetime The date and time to consider for invalidation
     * @param bool $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|Throwable
     */
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $datetime ??= new DateTime();
        $platform = $this->connection->getDatabasePlatform();

        $this->connection->beginTransaction();
        try {

            $invalidData = $this->query()
                ->where($this->getColumnName('valid') . ' < :datetime')
                ->setParameter('datetime', $datetime, 'datetime')
                ->fetchAllAssociative();

            if ([] !== $invalidData) {
                $this->connection->createQueryBuilder()
                    ->delete($this->tableName)
                    ->where($this->getColumnName('valid') . ' < :datetime')
                    ->setParameter('datetime', $datetime, 'datetime')
                    ->executeStatement();
            }

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        if ([] === $invalidData) {
            return [];
        }

        return array_map(fn(array $data) => $this->hydrate($data), $invalidData);
    }

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Generator that yields batches of invalid token data without hydration.
     *
     * @param positive-int $batchSize
     * @param int<0, max> $offset
     *
     * @return Generator<array<string, mixed>>
     *
     * @throws Exception
     */
    private function generateInvalidTokenBatches(DateTimeInterface $datetime, int $batchSize, int $offset): Generator
    {
        do {
            $qb = $this->query()
                ->where($this->getColumnName('valid') . ' < :datetime')
                ->setParameter('datetime', $datetime, 'datetime')
                ->setMaxResults($batchSize)
                ->setFirstResult($offset);

            $results = $qb->fetchAllAssociative();

            if ([] !== $results) {
                yield $results;
            }

            $offset += $batchSize;
        } while ([] !== $results);
    }

    private function query(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                $this->getColumnName('id'),
                $this->getColumnName('refreshToken'),
                $this->getColumnName('username'),
                $this->getColumnName('valid')
            )
            ->from($this->tableName);
    }
}
