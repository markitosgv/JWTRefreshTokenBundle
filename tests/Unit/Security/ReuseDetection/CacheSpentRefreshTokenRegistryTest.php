<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\ReuseDetection;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\CacheSpentRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A real pool rather than a mock: what matters here is that a value written under a key comes back
 * under the same key, which a mock would only assert about itself.
 */
final class CacheSpentRefreshTokenRegistryTest extends TestCase
{
    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
    }

    public function test_recognises_a_token_it_was_told_about(): void
    {
        $token = RefreshToken::createForUserWithTtl('a-spent-token', UserCreator::create('someone'), 600);
        $token->setFamily('the-chain-it-belonged-to');

        $registry = $this->registry();
        $registry->remember($token);

        $spent = $registry->recall('a-spent-token');

        $this->assertInstanceOf(SpentRefreshToken::class, $spent);
        $this->assertSame('the-chain-it-belonged-to', $spent->family);
        $this->assertSame('someone', $spent->username);
    }

    /**
     * The ordinary answer. Most tokens nobody has a row for are simply wrong, and treating those as
     * replays would end a session on every typo.
     */
    public function test_knows_nothing_about_a_token_that_was_never_spent(): void
    {
        $this->assertNull($this->registry()->recall('a-token-nobody-ever-had'));
    }

    public function test_keeps_spent_tokens_apart(): void
    {
        $registry = $this->registry();

        foreach (['one' => 'first-chain', 'two' => 'second-chain'] as $value => $family) {
            $token = RefreshToken::createForUserWithTtl($value, UserCreator::create('someone'), 600);
            $token->setFamily($family);
            $registry->remember($token);
        }

        $this->assertSame('first-chain', $registry->recall('one')?->family);
        $this->assertSame('second-chain', $registry->recall('two')?->family);
    }

    /**
     * A token class of an application's own need not have families, and the record is still worth
     * keeping: the username is enough for a listener to revoke by user instead.
     */
    public function test_remembers_a_token_that_belongs_to_no_chain(): void
    {
        $token = new class extends RefreshToken {};
        $token->setRefreshToken('a-spent-token');
        $token->setUsername('someone');

        $registry = $this->registry();
        $registry->remember($token);

        $spent = $registry->recall('a-spent-token');

        $this->assertInstanceOf(SpentRefreshToken::class, $spent);
        $this->assertNull($spent->family);
        $this->assertSame('someone', $spent->username);
    }

    /**
     * Nothing to key the record by, so there is nothing to record.
     */
    public function test_ignores_a_token_with_no_value(): void
    {
        $this->registry()->remember($this->createStub(RefreshTokenInterface::class));

        $this->assertSame([], $this->pool->getValues());
    }

    /**
     * The token is not the key. A refresh token is a credential, and cache keys turn up in file
     * names, in `redis-cli KEYS` output and in profiler dumps.
     */
    public function test_does_not_put_the_token_itself_in_the_cache(): void
    {
        $token = RefreshToken::createForUserWithTtl('a-token-worth-stealing', UserCreator::create('someone'), 600);
        $token->setFamily('a-chain');

        $this->registry()->remember($token);

        $stored = $this->pool->getValues();

        $this->assertNotSame([], $stored, 'Something should have been written');

        foreach (array_keys($stored) as $key) {
            $this->assertStringNotContainsString('a-token-worth-stealing', $key);
        }

        foreach ($stored as $value) {
            // ArrayAdapter keeps the values serialised, so this is the string that reaches the pool
            $this->assertIsString($value);
            $this->assertStringNotContainsString('a-token-worth-stealing', $value);
        }
    }

    /**
     * The pool belongs to the application, not to this bundle. Anything else found under the key is
     * treated as nothing having been recorded rather than handed on as a record.
     */
    public function test_ignores_something_else_stored_under_the_key(): void
    {
        $registry = $this->registry();

        $token = RefreshToken::createForUserWithTtl('a-spent-token', UserCreator::create('someone'), 600);
        $registry->remember($token);

        // Overwrite whatever key it chose with a value of the wrong shape
        $key = array_keys($this->pool->getValues())[0];
        $item = $this->pool->getItem($key);
        $item->set('not a spent token at all');
        $this->pool->save($item);

        $this->assertNull($registry->recall('a-spent-token'));
    }

    /**
     * The record is only worth keeping for as long as a replay could still arrive, and letting the
     * pool expire it is the whole reason a cache is the right place for this. Nothing here waits for
     * the clock; what is checked is that the window configured is the window asked for.
     */
    public function test_gives_the_record_the_configured_window(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(900);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item);

        (new CacheSpentRefreshTokenRegistry($pool, 900))
            ->remember(RefreshToken::createForUserWithTtl('a-spent-token', UserCreator::create('someone'), 600));
    }

    private function registry(): CacheSpentRefreshTokenRegistry
    {
        return new CacheSpentRefreshTokenRegistry($this->pool, 2592000);
    }
}
