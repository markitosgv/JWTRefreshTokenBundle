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
use Doctrine\DBAL\Query\QueryBuilder;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

final readonly class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * @var array<string, array{name: string, type: string}>
     */
    private array $columnConfig;

    /**
     * @param Connection $connection DBAL connection
     * @param positive-int $defaultBatchSize
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
    ) {
        $this->columnConfig = $columnConfig ?: TableSchemaManager::getDefaultColumnConfig();
    }

    /**
     * Get column name by alias.
     */
    private function getColumnName(string $alias): string
    {
        return $this->columnConfig[$alias]['name'] ?? $alias;
    }

    /**
     * Get properly quoted column identifier by alias.
     */
    private function quoteColumnIdentifier(string $alias): string
    {
        return $this->connection->getDatabasePlatform()->quoteIdentifier($this->getColumnName($alias));
    }

    /**
     * Get properly quoted table identifier.
     */
    private function quoteTableIdentifier(): string
    {
        return $this->connection->getDatabasePlatform()->quoteIdentifier($this->tableName);
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
        }, null, AbstractRefreshToken::class);

        $hydrator($instance, $data);

        return $instance;
    }

    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        $qb = $this->query()
            ->where($this->quoteColumnIdentifier('refreshToken') . ' = :refreshToken')
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
            ->where($this->quoteColumnIdentifier('username') . ' = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->orderBy($this->quoteColumnIdentifier('valid'), 'DESC');

        $data = $qb->fetchAssociative();
        if (false === $data) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $refreshTokenString = $refreshToken->getRefreshToken();
        if (null === $refreshTokenString) {
            throw new \InvalidArgumentException('Cannot save a refresh token without a token string.');
        }

        $token = $this->get($refreshTokenString);

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
                ->where($this->quoteColumnIdentifier('refreshToken') . ' = :refresh_token')
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
        $result = $this->connection->delete(
            $this->tableName,
            [$this->getColumnName('refreshToken') => $refreshToken->getRefreshToken()]
        );

        return (int)$result;
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * @param ?\DateTimeInterface $datetime The date and time to consider for invalidation
     * @param ?positive-int $batchSize Number of tokens to process per batch, defaults to the {@see $defaultBatchSize} property when not provided
     * @param int<0, max> $offset The offset to start processing from, defaults to 0
     * @param bool $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|\Throwable
     */
    public function revokeAllInvalidBatch(?\DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        $batchSize ??= $this->defaultBatchSize;
        $datetime ??= new \DateTime();
        $allRevokedData = [];

        $this->connection->beginTransaction();
        try {
            foreach ($this->generateInvalidTokenBatches($datetime, $batchSize, $offset) as $batchData) {
                $ids = array_column($batchData, $this->getColumnName('id'));

                if ([] === $ids) {
                    continue;
                }

                $this->connection->executeStatement(
                    sprintf(
                        'DELETE FROM %s WHERE %s IN (%s)',
                        $this->quoteTableIdentifier(),
                        $this->quoteColumnIdentifier('id'),
                        implode(',', array_fill(0, count($ids), '?'))
                    ),
                    $ids
                );

                $allRevokedData = array_merge($allRevokedData, $batchData);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return array_map($this->hydrate(...), $allRevokedData);
    }

    /**
     * Revokes all invalid (expired) refresh tokens.
     *
     * @param ?\DateTimeInterface $datetime The date and time to consider for invalidation
     * @param bool $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|\Throwable
     */
    public function revokeAllInvalid(?\DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $datetime ??= new \DateTime();

        $this->connection->beginTransaction();
        try {
            $invalidData = $this->query()
                ->where($this->quoteColumnIdentifier('valid') . ' < :datetime')
                ->setParameter('datetime', $datetime, 'datetime')
                ->fetchAllAssociative();

            if ([] !== $invalidData) {
                $this->connection->createQueryBuilder()
                    ->delete($this->tableName)
                    ->where($this->quoteColumnIdentifier('valid') . ' < :datetime')
                    ->setParameter('datetime', $datetime, 'datetime')
                    ->executeStatement();
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        if ([] === $invalidData) {
            return [];
        }

        return array_map($this->hydrate(...), $invalidData);
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
     * After each batch is deleted, records shift down, so we always fetch from the same offset.
     * The initial offset parameter allows starting from a specific position if needed.
     *
     * @param positive-int $batchSize
     * @param int<0, max>  $offset Starting offset for the first batch
     *
     * @return \Generator<int, array<int, array<string, mixed>>>
     *
     * @throws Exception
     */
    private function generateInvalidTokenBatches(\DateTimeInterface $datetime, int $batchSize, int $offset): \Generator
    {
        do {
            $qb = $this->query()
                ->where($this->quoteColumnIdentifier('valid') . ' < :datetime')
                ->setParameter('datetime', $datetime, 'datetime')
                ->setMaxResults($batchSize)
                ->setFirstResult($offset);

            $results = $qb->fetchAllAssociative();

            if ([] !== $results) {
                yield $results;
            }

            // Don't increment offset - after deletion, remaining records shift to fill the gap
        } while ([] !== $results);
    }

    private function query(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                $this->quoteColumnIdentifier('id'),
                $this->quoteColumnIdentifier('refreshToken'),
                $this->quoteColumnIdentifier('username'),
                $this->quoteColumnIdentifier('valid')
            )
            ->from($this->quoteTableIdentifier());
    }
}
