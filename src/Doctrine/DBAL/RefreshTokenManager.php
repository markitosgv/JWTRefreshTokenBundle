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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class RefreshTokenManager implements RefreshTokenManagerInterface, RevokeRefreshTokenManagerInterface
{
    /**
     * @var array<string, array{name: string, type: string}>
     */
    private array $columnConfig;

    /**
     * @param Connection                                       $connection       DBAL connection
     * @param positive-int                                     $defaultBatchSize
     * @param string                                           $tableName        Name of the refresh tokens table
     * @param class-string<RefreshTokenInterface>              $class            Fully qualified class name for refresh token instances
     * @param array<string, array{name: string, type: string}> $columnConfig     Map of aliases to column configuration ['alias' => ['name' => 'column_name', 'type' => Types::STRING]]
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private Connection $connection,
        private int $defaultBatchSize,
        private string $tableName,
        private string $class,
        array $columnConfig = []
    ) {
        $this->columnConfig = $columnConfig ?: TableSchemaManager::getDefaultColumnConfig();
    }

    /**
     * Get column name by alias.
     *
     * @psalm-mutation-free
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
        return $this->quote($this->getColumnName($alias));
    }

    /**
     * Get properly quoted table identifier.
     */
    private function quoteTableIdentifier(): string
    {
        return $this->quote($this->tableName);
    }

    /**
     * Quotes one identifier.
     *
     * quoteSingleIdentifier() replaces quoteIdentifier() in DBAL 4, which is deprecated there and
     * goes away in 5. The names are validated as single identifiers when the bundle is configured,
     * so there is no qualified name to split, and the old method stays for DBAL 3.
     */
    private function quote(string $identifier): string
    {
        $platform = $this->connection->getDatabasePlatform();

        if (method_exists($platform, 'quoteSingleIdentifier')) {
            return $platform->quoteSingleIdentifier($identifier);
        }

        return $platform->quoteIdentifier($identifier);
    }

    /**
     * Builds a token out of one row.
     *
     * Everything the model exposes a setter for goes through it; only the identifier does not have
     * one, as the mappings fill it in and there is no mapping here.
     *
     * @param array<string, mixed> $data Raw data from database
     */
    private function hydrate(array $data): RefreshTokenInterface
    {
        $class = $this->class;
        $instance = new $class();

        $refreshToken = $data[$this->getColumnName('refreshToken')] ?? $data['refresh_token'] ?? null;
        $username = $data[$this->getColumnName('username')] ?? $data['username'] ?? null;
        $valid = $this->connection->convertToPHPValue(
            $data[$this->getColumnName('valid')] ?? $data['valid'] ?? null,
            $this->columnConfig['valid']['type'] ?? 'datetime'
        );

        if (is_string($refreshToken)) {
            $instance->setRefreshToken($refreshToken);
        }

        if (is_string($username)) {
            $instance->setUsername($username);
        }

        if ($valid instanceof \DateTimeInterface) {
            $instance->setValid($valid);
        }

        $this->assignIdentifier($instance, $data[$this->getColumnName('id')] ?? $data['id'] ?? null);

        return $instance;
    }

    /**
     * Assigns the identifier the database gave the row.
     *
     * An implementation is free not to have the property, in which case there is nothing to fill.
     */
    private function assignIdentifier(RefreshTokenInterface $token, mixed $id): void
    {
        if (null === $id) {
            return;
        }

        $reflection = new \ReflectionObject($token);

        if (!$reflection->hasProperty('id')) {
            return;
        }

        $reflection->getProperty('id')->setValue($token, $id);
    }

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        $qb = $this->query()
            ->where($this->quoteColumnIdentifier('refreshToken').' = :refreshToken')
            ->setParameter('refreshToken', $refreshToken)
            ->setMaxResults(1);

        $data = $qb->fetchAssociative();
        if (false === $data) {
            return null;
        }

        return $this->hydrate($data);
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        $qb = $this->query()
            ->where($this->quoteColumnIdentifier('username').' = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->orderBy($this->quoteColumnIdentifier('valid'), 'DESC');

        $data = $qb->fetchAssociative();
        if (false === $data) {
            return null;
        }

        return $this->hydrate($data);
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $refreshTokenString = $refreshToken->getRefreshToken();

        if (null === $refreshTokenString) {
            throw new \InvalidArgumentException('Cannot save a refresh token without a token string.');
        }

        $parameters = [
            'refresh_token' => $refreshTokenString,
            'username' => $refreshToken->getUsername(),
            'valid' => $refreshToken->getValid(),
        ];
        $types = ['valid' => 'datetime'];

        // Updating first tells us whether the row is there, so the read the other way round needs
        // does not happen. The token string is the natural key, so at most one row matches.
        $updated = $this->connection->createQueryBuilder()
            ->update($this->quoteTableIdentifier())
            ->set($this->quoteColumnIdentifier('username'), ':username')
            ->set($this->quoteColumnIdentifier('valid'), ':valid')
            ->where($this->quoteColumnIdentifier('refreshToken').' = :refresh_token')
            ->setParameters($parameters, $types)
            ->executeStatement();

        if ($updated > 0) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->insert($this->quoteTableIdentifier())
            ->values([
                $this->quoteColumnIdentifier('refreshToken') => ':refresh_token',
                $this->quoteColumnIdentifier('username') => ':username',
                $this->quoteColumnIdentifier('valid') => ':valid',
            ])
            ->setParameters($parameters, $types)
            ->executeStatement();
    }

    /**
     * Deletes the given refresh token and returns the number of rows affected.
     *
     * @return int Number of rows deleted (should be 1 if deleted, 0 if not found)
     *
     * @throws Exception
     */
    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        $result = $this->connection->delete(
            $this->quoteTableIdentifier(),
            [$this->quoteColumnIdentifier('refreshToken') => $refreshToken->getRefreshToken()]
        );

        return (int) $result;
    }

    #[\Override]
    public function revokeAllForUser(UserInterface $user): int
    {
        return (int) $this->connection->delete(
            $this->quoteTableIdentifier(),
            [$this->quoteColumnIdentifier('username') => $user->getUserIdentifier()]
        );
    }

    #[\Override]
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int
    {
        // The ids to delete are read first, since a DELETE takes neither an order nor an offset,
        // and the ones to keep are the newest, which is an offset away from the front
        $stale = $this->connection->createQueryBuilder()
            ->select($this->quoteColumnIdentifier('id'))
            ->from($this->quoteTableIdentifier())
            ->where($this->quoteColumnIdentifier('username').' = :username')
            ->setParameter('username', $user->getUserIdentifier())
            ->orderBy($this->quoteColumnIdentifier('valid'), 'DESC')
            ->setFirstResult($keep)
            // Every driver needs a limit before it will honour an offset, and no user has this many
            ->setMaxResults(PHP_INT_MAX)
            ->fetchFirstColumn();

        if ([] === $stale) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IN (?)', $this->quoteTableIdentifier(), $this->quoteColumnIdentifier('id')),
            [$stale],
            [ArrayParameterType::INTEGER]
        );
    }

    /**
     * Revokes all invalid (expired) refresh tokens in batches.
     *
     * @param ?\DateTimeInterface $datetime  The date and time to consider for invalidation
     * @param ?positive-int       $batchSize Number of tokens to process per batch, defaults to the {@see $defaultBatchSize} property when not provided
     * @param int<0, max>         $offset    The offset to start processing from, defaults to 0
     * @param bool                $andFlush  Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|\Throwable
     */
    #[\Override]
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
     * @param bool                $andFlush Whether to flush the object manager after revoking
     *
     * @return RefreshTokenInterface[]
     *
     * @throws Exception|\Throwable
     */
    #[\Override]
    public function revokeAllInvalid(?\DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $datetime ??= new \DateTime();

        $this->connection->beginTransaction();
        try {
            $invalidData = $this->query()
                ->where($this->quoteColumnIdentifier('valid').' < :datetime')
                ->setParameter('datetime', $datetime, 'datetime')
                ->fetchAllAssociative();

            if ([] !== $invalidData) {
                $this->connection->createQueryBuilder()
                    ->delete($this->quoteTableIdentifier())
                    ->where($this->quoteColumnIdentifier('valid').' < :datetime')
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
    #[\Override]
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
     * @param int<0, max>  $offset    Starting offset for the first batch
     *
     * @return \Generator<int, array<int, array<string, mixed>>>
     *
     * @throws Exception
     */
    private function generateInvalidTokenBatches(\DateTimeInterface $datetime, int $batchSize, int $offset): \Generator
    {
        do {
            $qb = $this->query()
                ->where($this->quoteColumnIdentifier('valid').' < :datetime')
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
