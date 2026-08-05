<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\TokenWithoutAnIdentifier;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\TestCase;

class RefreshTokenManagerTest extends TestCase
{
    private Connection $connection;
    private RefreshTokenManager $manager;
    private TableSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->schemaManager = new TableSchemaManager(
            $this->connection,
            'refresh_tokens',
            []
        );
        $this->manager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            'refresh_tokens',
            RefreshToken::class
        );
        $this->schemaManager->createTable(true);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testReturnsTheTokenOfAUserThatExpiresLast(): void
    {
        foreach ([1, 30, 7] as $days) {
            $token = new RefreshToken();
            $token->setRefreshToken(sprintf('token-valid-%d-days', $days));
            $token->setUsername('someone');
            $token->setValid(new \DateTime(sprintf('+%d days', $days)));

            $this->manager->save($token);
        }

        $other = new RefreshToken();
        $other->setRefreshToken('token-of-another-user');
        $other->setUsername('somebody-else');
        $other->setValid(new \DateTime('+90 days'));
        $this->manager->save($other);

        $last = $this->manager->getLastFromUsername('someone');

        $this->assertNotNull($last);
        $this->assertSame('token-valid-30-days', $last->getRefreshToken(), 'The one expiring last should win, whatever order they were stored in');
        $this->assertSame('someone', $last->getUsername());
    }

    public function testReturnsNoTokenForAUserWithoutOne(): void
    {
        $this->assertNull($this->manager->getLastFromUsername('nobody'));
    }

    public function testKeepsTheIdentifierTheDatabaseAssigned(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('token-with-an-id');
        $token->setUsername('testuser');
        $token->setValid(new \DateTime('+1 day'));

        $this->manager->save($token);

        $stored = $this->manager->get('token-with-an-id');

        $this->assertNotNull($stored);
        $this->assertNotNull($stored->getId(), 'The row identifier should reach the model');
    }

    public function testUpdatesInPlaceInsteadOfInsertingTwice(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('token-saved-twice');
        $token->setUsername('first');
        $token->setValid(new \DateTime('+1 day'));

        $this->manager->save($token);

        $token->setUsername('second');
        $this->manager->save($token);

        /** @var int|string $count */
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM refresh_tokens WHERE refresh_token = ?',
            ['token-saved-twice']
        );

        $this->assertSame(1, (int) $count, 'Saving the same token again should update the row, not add one');
        $this->assertSame('second', $this->manager->get('token-saved-twice')?->getUsername());
    }

    public function testSaveAndRetrieveToken(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('test-token-123');
        $token->setUsername('testuser');
        $token->setValid(new \DateTime('+1 hour'));

        $this->manager->save($token);
        $retrieved = $this->manager->get('test-token-123');

        $this->assertNotNull($retrieved);
        $this->assertSame('test-token-123', $retrieved->getRefreshToken());
        $this->assertSame('testuser', $retrieved->getUsername());
    }

    public function testDeleteToken(): void
    {
        $token = new RefreshToken();
        $token->setRefreshToken('delete-me');
        $token->setUsername('user');
        $token->setValid(new \DateTime('+1 hour'));

        $this->manager->save($token);
        $this->manager->delete($token);

        $this->assertNull($this->manager->get('delete-me'));
    }

    public function testUpdateExistingToken(): void
    {
        // Save initial token
        $token = new RefreshToken();
        $token->setRefreshToken('update-token');
        $token->setUsername('original-user');
        $originalValid = new \DateTime('+1 hour');
        $token->setValid($originalValid);

        $this->manager->save($token);

        // Verify initial save
        $retrieved = $this->manager->get('update-token');
        $this->assertNotNull($retrieved);
        $this->assertSame('original-user', $retrieved->getUsername());
        $this->assertEquals($originalValid->getTimestamp(), $retrieved->getValid()?->getTimestamp());

        // Modify the token
        $token->setUsername('updated-user');
        $newValid = new \DateTime('+2 hours');
        $token->setValid($newValid);

        // Save again (should UPDATE, not INSERT)
        $this->manager->save($token);

        // Verify the update occurred
        $updated = $this->manager->get('update-token');
        $this->assertNotNull($updated);
        $this->assertSame('updated-user', $updated->getUsername());
        $this->assertEquals($newValid->getTimestamp(), $updated->getValid()?->getTimestamp());

        // Verify we still have only one row (UPDATE, not INSERT)
        $allTokens = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('refresh_tokens')
            ->where('refresh_token = :token')
            ->setParameter('token', 'update-token')
            ->fetchAllAssociative();

        $this->assertCount(1, $allTokens, 'Should have exactly one token (updated, not inserted)');
    }

    public function testRevokeExpiredTokens(): void
    {
        $valid = new RefreshToken();
        $valid->setRefreshToken('valid-token');
        $valid->setUsername('user1');
        $valid->setValid(new \DateTime('+1 hour'));
        $this->manager->save($valid);

        $expired = new RefreshToken();
        $expired->setRefreshToken('expired-token');
        $expired->setUsername('user2');
        $expired->setValid(new \DateTime('-1 hour'));
        $this->manager->save($expired);

        $revoked = $this->manager->revokeAllInvalid();

        $this->assertCount(1, $revoked);
        $this->assertSame('expired-token', $revoked[0]->getRefreshToken());
        $this->assertNotNull($this->manager->get('valid-token'));
        $this->assertNull($this->manager->get('expired-token'));
    }

    public function testRevokeAllInvalidBatchWithSingleBatch(): void
    {
        // Create 3 expired tokens and 2 valid tokens
        for ($i = 1; $i <= 3; ++$i) {
            $expired = new RefreshToken();
            $expired->setRefreshToken("expired-{$i}");
            $expired->setUsername("user{$i}");
            $expired->setValid(new \DateTime('-1 hour'));
            $this->manager->save($expired);
        }

        for ($i = 1; $i <= 2; ++$i) {
            $valid = new RefreshToken();
            $valid->setRefreshToken("valid-{$i}");
            $valid->setUsername("validuser{$i}");
            $valid->setValid(new \DateTime('+1 hour'));
            $this->manager->save($valid);
        }

        // Revoke with large batch size (all in one batch)
        $revoked = $this->manager->revokeAllInvalidBatch(null, 100);

        $this->assertCount(3, $revoked, 'Should revoke 3 expired tokens');

        // Verify valid tokens remain
        $this->assertNotNull($this->manager->get('valid-1'));
        $this->assertNotNull($this->manager->get('valid-2'));

        // Verify expired tokens are gone
        $this->assertNull($this->manager->get('expired-1'));
        $this->assertNull($this->manager->get('expired-2'));
        $this->assertNull($this->manager->get('expired-3'));
    }

    public function testRevokeAllInvalidBatchWithMultipleBatches(): void
    {
        // Create 10 expired tokens
        for ($i = 1; $i <= 10; ++$i) {
            $expired = new RefreshToken();
            $expired->setRefreshToken("expired-{$i}");
            $expired->setUsername("user{$i}");
            $expired->setValid(new \DateTime('-1 hour'));
            $this->manager->save($expired);
        }

        // Create 3 valid tokens
        for ($i = 1; $i <= 3; ++$i) {
            $valid = new RefreshToken();
            $valid->setRefreshToken("valid-{$i}");
            $valid->setUsername("validuser{$i}");
            $valid->setValid(new \DateTime('+1 hour'));
            $this->manager->save($valid);
        }

        // Revoke in batches of 3 (should process 4 batches: 3+3+3+1)
        $revoked = $this->manager->revokeAllInvalidBatch(null, 3);

        $this->assertCount(10, $revoked, 'Should revoke all 10 expired tokens across multiple batches');

        // Verify all valid tokens remain
        for ($i = 1; $i <= 3; ++$i) {
            $this->assertNotNull($this->manager->get("valid-{$i}"));
        }

        // Verify all expired tokens are gone
        for ($i = 1; $i <= 10; ++$i) {
            $this->assertNull($this->manager->get("expired-{$i}"));
        }

        // Verify total count
        $totalTokens = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens');
        $this->assertEquals(3, $totalTokens, 'Should have exactly 3 valid tokens remaining');
    }

    public function testRevokeAllInvalidBatchWithNoExpiredTokens(): void
    {
        // Create only valid tokens
        for ($i = 1; $i <= 5; ++$i) {
            $valid = new RefreshToken();
            $valid->setRefreshToken("valid-{$i}");
            $valid->setUsername("user{$i}");
            $valid->setValid(new \DateTime('+1 hour'));
            $this->manager->save($valid);
        }

        $revoked = $this->manager->revokeAllInvalidBatch();

        $this->assertCount(0, $revoked, 'Should not revoke any tokens when none are expired');

        // Verify all tokens remain
        $totalTokens = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens');
        $this->assertEquals(5, $totalTokens);
    }

    public function testRevokeAllInvalidBatchWithCustomDateTime(): void
    {
        // Create tokens with different expiration dates
        $token1 = new RefreshToken();
        $token1->setRefreshToken('expires-in-2-hours');
        $token1->setUsername('user1');
        $token1->setValid(new \DateTime('+2 hours'));
        $this->manager->save($token1);

        $token2 = new RefreshToken();
        $token2->setRefreshToken('expires-in-1-hour');
        $token2->setUsername('user2');
        $token2->setValid(new \DateTime('+1 hour'));
        $this->manager->save($token2);

        $token3 = new RefreshToken();
        $token3->setRefreshToken('expired-1-hour-ago');
        $token3->setUsername('user3');
        $token3->setValid(new \DateTime('-1 hour'));
        $this->manager->save($token3);

        // Revoke tokens expiring before 90 minutes from now
        $cutoffTime = new \DateTime('+90 minutes');
        $revoked = $this->manager->revokeAllInvalidBatch($cutoffTime);

        $this->assertCount(2, $revoked, 'Should revoke tokens expiring within 90 minutes');

        // Verify only the 2-hour token remains
        $this->assertNotNull($this->manager->get('expires-in-2-hours'));
        $this->assertNull($this->manager->get('expires-in-1-hour'));
        $this->assertNull($this->manager->get('expired-1-hour-ago'));
    }

    public function testRevokeAllInvalidBatchWithOffset(): void
    {
        // Create 10 expired tokens
        for ($i = 1; $i <= 10; ++$i) {
            $expired = new RefreshToken();
            $expired->setRefreshToken("expired-{$i}");
            $expired->setUsername("user{$i}");
            $expired->setValid(new \DateTime('-1 hour'));
            $this->manager->save($expired);
        }

        // Skip first 5 tokens (offset=5), batch size 10
        $revoked = $this->manager->revokeAllInvalidBatch(null, 10, 5);

        // Should revoke 5 tokens (skipping first 5)
        $this->assertCount(5, $revoked, 'Should revoke 5 tokens starting from offset 5');

        // Verify first 5 tokens still exist
        for ($i = 1; $i <= 5; ++$i) {
            $this->assertNotNull($this->manager->get("expired-{$i}"), "Token expired-{$i} should still exist");
        }

        // Verify last 5 tokens are deleted
        for ($i = 6; $i <= 10; ++$i) {
            $this->assertNull($this->manager->get("expired-{$i}"), "Token expired-{$i} should be deleted");
        }
    }

    public function testRevokeAllInvalidBatchRethrowsExceptionsOnDatabaseError(): void
    {
        // Create some expired tokens
        for ($i = 1; $i <= 5; ++$i) {
            $expired = new RefreshToken();
            $expired->setRefreshToken("expired-{$i}");
            $expired->setUsername("user{$i}");
            $expired->setValid(new \DateTime('-1 hour'));
            $this->manager->save($expired);
        }

        // Close the connection to simulate a database error during batch processing
        $this->connection->close();

        // Verify that exceptions are properly caught and rethrown
        $this->expectException(\Throwable::class);
        $this->manager->revokeAllInvalidBatch();
    }

    public function testRevokeAllInvalidBatchReturnsHydratedObjects(): void
    {
        // Create expired tokens
        $token = new RefreshToken();
        $token->setRefreshToken('expired-token');
        $token->setUsername('testuser');
        $validTime = new \DateTime('-1 hour');
        $token->setValid($validTime);
        $this->manager->save($token);

        $revoked = $this->manager->revokeAllInvalidBatch();

        $this->assertCount(1, $revoked);
        $this->assertInstanceOf(RefreshToken::class, $revoked[0]);
        $this->assertSame('expired-token', $revoked[0]->getRefreshToken());
        $this->assertSame('testuser', $revoked[0]->getUsername());
        $this->assertInstanceOf(\DateTimeInterface::class, $revoked[0]->getValid());
    }

    public function testRefusesToSaveATokenWithoutItsValue(): void
    {
        $token = new RefreshToken();
        $token->setUsername('someone');
        $token->setValid(new \DateTime('+600 seconds'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without a token string');

        $this->manager->save($token);
    }

    /**
     * The interface asks for no identifier, and the backend fills an `id` property only when the
     * model has one. This is the model that does not.
     */
    public function testStoresATokenModelWithoutAnIdentifier(): void
    {
        $manager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            'refresh_tokens',
            TokenWithoutAnIdentifier::class,
            []
        );

        $manager->save(TokenWithoutAnIdentifier::createForUserWithTtl('a-token-without-an-id', UserCreator::create('someone'), 600));

        $stored = $manager->get('a-token-without-an-id');

        $this->assertNotNull($stored);
        $this->assertNull($stored->getId());
    }

    /**
     * A `dbal_columns` map written before families existed names no family column, and the table it
     * describes has none. Reading or writing one would fail every query against such a table, so a
     * map without it is taken to mean the backend has no families rather than that it should make
     * the column up.
     */
    public function testWorksAgainstATableWithNoFamilyColumn(): void
    {
        $columns = TableSchemaManager::getDefaultColumnConfig();
        unset($columns['family']);

        (new TableSchemaManager($this->connection, 'tokens_without_families', $columns))->createTable(true);

        $manager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            'tokens_without_families',
            RefreshToken::class,
            $columns
        );

        $token = RefreshToken::createForUserWithTtl('a-token-with-nowhere-to-put-a-family', UserCreator::create('someone'), 600);
        $token->setFamily('a-family-that-cannot-be-stored');

        $manager->save($token);

        $stored = $manager->get('a-token-with-nowhere-to-put-a-family');

        $this->assertNotNull($stored, 'The token stores and reads back as it always did');
        $this->assertInstanceOf(RefreshToken::class, $stored);
        $this->assertNull($stored->getFamily(), 'There was nowhere to keep the family, so none comes back');
    }

    /**
     * Reporting nothing revoked would read as "that chain held no tokens", which is the answer a
     * table with no family column would give to every call ever made.
     */
    public function testRefusesToRevokeAChainInATableThatCannotRecordOne(): void
    {
        $columns = TableSchemaManager::getDefaultColumnConfig();
        unset($columns['family']);

        (new TableSchemaManager($this->connection, 'tokens_without_families', $columns))->createTable(true);

        $manager = new RefreshTokenManager(
            $this->connection,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE,
            'tokens_without_families',
            RefreshToken::class,
            $columns
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('names no "family" column');

        $manager->revokeFamily('a-chain');
    }

    /**
     * Saving twice takes the update path rather than the insert, which writes the family separately.
     */
    public function testUpdatesTheFamilyOfATokenAlreadyStored(): void
    {
        $token = RefreshToken::createForUserWithTtl('a-token-saved-twice', UserCreator::create('someone'), 600);
        $token->setFamily('the-chain-it-started-in');

        $this->manager->save($token);

        $token->setFamily('the-chain-it-was-moved-to');
        $this->manager->save($token);

        $stored = $this->manager->get('a-token-saved-twice');

        $this->assertInstanceOf(RefreshToken::class, $stored);
        $this->assertSame('the-chain-it-was-moved-to', $stored->getFamily());
    }

    /**
     * The batch revocation runs in a transaction, so a statement failing part way through has to
     * leave the table as it was rather than half cleared.
     */
    public function testRollsTheBatchBackWhenAStatementFails(): void
    {
        $this->manager->save(RefreshToken::createForUserWithTtl('expired', UserCreator::create('someone'), -600));

        $this->connection->executeStatement('DROP TABLE refresh_tokens');

        $this->expectException(\Throwable::class);

        $this->manager->revokeAllInvalidBatch(null, 2);
    }

    /**
     * The same for the unbatched revocation, which runs its own transaction.
     */
    public function testRollsTheWholeRevocationBackWhenAStatementFails(): void
    {
        $this->manager->save(RefreshToken::createForUserWithTtl('expired', UserCreator::create('someone'), -600));

        $this->connection->executeStatement('DROP TABLE refresh_tokens');

        $this->expectException(\Throwable::class);

        $this->manager->revokeAllInvalid();
    }
}
