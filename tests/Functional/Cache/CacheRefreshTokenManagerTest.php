<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Cache;

use Gesdinet\JWTRefreshTokenBundle\Cache\CacheRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The shared contracts are deliberately not applied here.
 *
 * They assert things a pool cannot do — finding a user's newest token, reporting which tokens have
 * expired — and this manager does not claim to. What is checked instead is that the things it does
 * claim work, that expiry is left to the pool, and that the rest refuses out loud rather than
 * answering in a way a caller would act on.
 */
final class CacheRefreshTokenManagerTest extends TestCase
{
    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
    }

    public function test_reads_back_a_token_it_stored(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-stored-token', UserCreator::create('someone'), 600));

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

    public function test_keeps_the_chain_a_token_belongs_to(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token-in-a-chain', UserCreator::create('someone'), 600);
        $token->setFamily('the-chain');
        $token->setFamilyValid(new \DateTime()->setTimestamp(time() + 86400));

        $manager->save($token);

        $stored = $manager->get('a-token-in-a-chain');

        $this->assertInstanceOf(RefreshToken::class, $stored);
        $this->assertSame('the-chain', $stored->getFamily());
        $this->assertSame($token->getFamilyValid()?->getTimestamp(), $stored->getFamilyValid()?->getTimestamp());
    }

    public function test_deletes_a_stored_token_and_reports_the_row(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token-to-delete', UserCreator::create('someone'), 600);
        $manager->save($token);

        $this->assertSame(1, $manager->delete($token));
        $this->assertNull($manager->get('a-token-to-delete'));
    }

    public function test_reports_no_row_when_deleting_a_token_that_is_not_stored(): void
    {
        $token = RefreshToken::createForUserWithTtl('a-token-never-stored', UserCreator::create('someone'), 600);

        $this->assertSame(0, $this->manager()->delete($token));
    }

    /**
     * What tells a single-use rotation it was the one that spent the token.
     */
    public function test_reports_nothing_deleted_when_the_token_went_first(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token-deleted-twice', UserCreator::create('someone'), 600);
        $manager->save($token);

        $this->assertSame(1, $manager->delete($token));
        $this->assertSame(0, $manager->delete($token));
    }

    public function test_reports_nothing_deleted_for_a_token_with_no_value(): void
    {
        $this->assertSame(0, $this->manager()->delete(new RefreshToken()));
    }

    public function test_refuses_to_store_a_token_with_no_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without a token string');

        $this->manager()->save(new RefreshToken());
    }

    /**
     * The reason a cache suits this at all: nothing has to be scheduled to remove expired tokens,
     * because the pool was told when each one stops being worth keeping.
     */
    public function test_leaves_the_expiry_to_the_pool(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('an-expired-token', UserCreator::create('someone'), -600));

        $this->assertNull($manager->get('an-expired-token'), 'The pool should have dropped it on the way in');
    }

    /**
     * Not a stub: there is genuinely nothing to revoke, since the pool has already dropped whatever
     * this would have looked for.
     */
    public function test_has_nothing_to_revoke_because_expiry_already_happened(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('an-expired-token', UserCreator::create('someone'), -600));
        $manager->save(RefreshToken::createForUserWithTtl('a-live-token', UserCreator::create('someone'), 600));

        $this->assertSame([], $manager->revokeAllInvalid());
        $this->assertSame([], $manager->revokeAllInvalidBatch());
        $this->assertNotNull($manager->get('a-live-token'), 'And the live one is untouched');
    }

    /**
     * Null would read as "this user has no tokens", which is a different thing from "this cannot be
     * known" and is the answer a caller would go on to act on.
     */
    public function test_says_it_cannot_look_a_token_up_by_user(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be enumerated');

        $this->manager()->getLastFromUsername('someone');
    }

    public function test_knows_the_class_it_stores(): void
    {
        $this->assertSame(RefreshToken::class, $this->manager()->getClass());
    }

    /**
     * A pool is the application's, and anything else found under the key is treated as nothing being
     * stored rather than hydrated into a token.
     */
    public function test_ignores_something_else_stored_under_the_key(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-stored-token', UserCreator::create('someone'), 600));

        $key = array_keys($this->pool->getValues())[0];
        $item = $this->pool->getItem($key);
        $item->set('not a refresh token at all');
        $this->pool->save($item);

        $this->assertNull($manager->get('a-stored-token'));
    }

    public function test_ignores_a_record_missing_what_a_token_needs(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-stored-token', UserCreator::create('someone'), 600));

        $key = array_keys($this->pool->getValues())[0];
        $item = $this->pool->getItem($key);
        $item->set(['refreshToken' => 'a-stored-token']);
        $this->pool->save($item);

        $this->assertNull($manager->get('a-stored-token'));
    }

    /**
     * Cache keys turn up in file names, in `redis-cli KEYS` output and in profiler dumps.
     */
    public function test_does_not_use_the_token_itself_as_the_key(): void
    {
        $this->manager()->save(RefreshToken::createForUserWithTtl('a-token-worth-stealing', UserCreator::create('someone'), 600));

        foreach (array_keys($this->pool->getValues()) as $key) {
            $this->assertStringNotContainsString('a-token-worth-stealing', $key);
        }
    }

    private function manager(): CacheRefreshTokenManager
    {
        return new CacheRefreshTokenManager($this->pool, RefreshToken::class);
    }
}
