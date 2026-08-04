<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * What every refresh token manager has to do, whatever it stores the tokens in.
 *
 * The bundle ships three implementations, and each one used to be tested on its own, which is how
 * they came to disagree: the batch revocation returned nothing and skipped half the tokens through
 * the object manager while the DBAL one was right, and deleting a token that was not stored
 * reported a deleted row with the ODM and none with the ORM.
 *
 * Only RefreshTokenManagerInterface is used here, so a test case gets the whole contract by naming
 * the manager it wants it run against.
 */
trait RefreshTokenManagerContract
{
    /**
     * @param positive-int $batchSize
     */
    abstract protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface;

    public function test_reads_back_a_token_it_stored(): void
    {
        $manager = $this->manager();

        $this->storeToken($manager, 'a-stored-token', 'someone', 600);

        $stored = $manager->get('a-stored-token');

        $this->assertNotNull($stored);
        $this->assertSame('a-stored-token', $stored->getRefreshToken());
        $this->assertSame('someone', $stored->getUsername());
        $this->assertTrue($stored->isValid());
    }

    public function test_has_no_token_for_a_string_it_never_stored(): void
    {
        $this->assertNull($this->manager()->get('a-token-that-was-never-issued'));
    }

    public function test_returns_the_token_of_a_user_that_expires_last(): void
    {
        $manager = $this->manager();

        // Stored out of order, so an implementation returning the first row fails
        $this->storeToken($manager, 'expires-in-a-day', 'someone', 86400);
        $this->storeToken($manager, 'expires-in-a-month', 'someone', 2592000);
        $this->storeToken($manager, 'expires-in-a-week', 'someone', 604800);
        $this->storeToken($manager, 'belongs-to-somebody-else', 'somebody-else', 7776000);

        $last = $manager->getLastFromUsername('someone');

        $this->assertNotNull($last);
        $this->assertSame('expires-in-a-month', $last->getRefreshToken());
    }

    public function test_has_no_last_token_for_a_user_without_one(): void
    {
        $this->assertNull($this->manager()->getLastFromUsername('nobody'));
    }

    public function test_storing_the_same_token_again_does_not_add_another(): void
    {
        $manager = $this->manager();

        $token = $this->storeToken($manager, 'a-token-stored-twice', 'someone', 600);

        $manager->save($token);

        $this->assertCount(1, $manager->revokeAllInvalid(new \DateTime('+1 year')), 'The token should be stored once');
    }

    public function test_deletes_a_stored_token_and_reports_the_row(): void
    {
        $manager = $this->manager();

        $token = $this->storeToken($manager, 'a-token-to-delete', 'someone', 600);

        $this->assertSame(1, $manager->delete($token));
        $this->assertNull($manager->get('a-token-to-delete'));
    }

    public function test_reports_no_row_when_deleting_a_token_that_is_not_stored(): void
    {
        $manager = $this->manager();

        $class = $manager->getClass();
        $token = $class::createForUserWithTtl('a-token-never-stored', UserCreator::create('someone'), 600);

        $this->assertSame(0, $manager->delete($token), 'Nothing was deleted, so nothing should be reported');
    }

    /**
     * What two callers racing for the same token need to tell them apart: the one that lost is told
     * nothing was deleted, rather than both being told they deleted it because both had read it
     * back first. Deleting behind the manager stands in for the other caller getting there first.
     */
    public function test_reports_nothing_deleted_when_the_token_went_first(): void
    {
        $manager = $this->manager();

        $token = $this->storeToken($manager, 'a-token-deleted-twice', 'someone', 600);

        $this->assertSame(1, $manager->delete($token), 'The caller that got there first deleted it');
        $this->assertSame(0, $manager->delete($token), 'The one that came second deleted nothing');
    }

    public function test_revokes_the_expired_tokens_and_leaves_the_rest(): void
    {
        $manager = $this->manager();

        $this->storeToken($manager, 'expired-one', 'someone', -600);
        $this->storeToken($manager, 'expired-two', 'someone', -300);
        $this->storeToken($manager, 'still-valid', 'someone', 600);

        $revoked = $manager->revokeAllInvalid();

        $this->assertCount(2, $revoked);
        $this->assertNull($manager->get('expired-one'));
        $this->assertNotNull($manager->get('still-valid'), 'A token that has not expired should survive');
    }

    public function test_has_nothing_to_revoke_when_no_token_expired(): void
    {
        $manager = $this->manager();

        $this->storeToken($manager, 'still-valid', 'someone', 600);

        $this->assertCount(0, $manager->revokeAllInvalid());
    }

    /**
     * Each batch is deleted before the next is read, so the tokens left shift down. An
     * implementation paging the offset forward skips one batch for every batch it deletes, and one
     * returning the batch it read last returns nothing, since that one is empty by then.
     */
    public function test_revokes_every_expired_token_across_batches(): void
    {
        $manager = $this->manager(2);

        for ($i = 1; $i <= 5; ++$i) {
            $this->storeToken($manager, sprintf('expired-%d', $i), 'someone', -600);
        }

        $revoked = $manager->revokeAllInvalidBatch(null, 2);

        $this->assertCount(5, $revoked, 'Every expired token should be revoked and reported');
        $this->assertCount(0, $manager->revokeAllInvalid(), 'No expired token should be left behind');
    }

    public function test_knows_the_class_it_stores(): void
    {
        $class = $this->manager()->getClass();

        $this->assertTrue(is_subclass_of($class, RefreshTokenInterface::class));
    }

    private function storeToken(RefreshTokenManagerInterface $manager, string $token, string $username, int $ttl): RefreshTokenInterface
    {
        $class = $manager->getClass();
        $refreshToken = $class::createForUserWithTtl($token, UserCreator::create($username), $ttl);

        $manager->save($refreshToken);

        return $refreshToken;
    }
}
