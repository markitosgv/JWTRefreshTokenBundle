<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Revocation;

use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\CacheJWTRevocationRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheJWTRevocationRegistryTest extends TestCase
{
    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
    }

    public function test_a_user_who_was_never_revoked_has_no_mark(): void
    {
        $this->assertNull($this->registry()->revokedBefore('someone'));
    }

    public function test_reads_back_the_moment_a_user_was_revoked(): void
    {
        $at = new \DateTimeImmutable('@'.(time() - 300));

        $registry = $this->registry();
        $registry->revokeIssuedBefore('someone', $at);

        $this->assertSame($at->getTimestamp(), $registry->revokedBefore('someone'));
    }

    public function test_keeps_users_apart(): void
    {
        $registry = $this->registry();

        $registry->revokeIssuedBefore('someone', new \DateTimeImmutable('@1000'));

        $this->assertSame(1000, $registry->revokedBefore('someone'));
        $this->assertNull($registry->revokedBefore('somebody-else'), 'Revoking one user must not sign out another');
    }

    /**
     * Revoking again moves the mark forward rather than leaving the older one, which would keep
     * accepting the JWTs issued between the two.
     */
    public function test_the_latest_revocation_is_the_one_that_counts(): void
    {
        $registry = $this->registry();

        $registry->revokeIssuedBefore('someone', new \DateTimeImmutable('@1000'));
        $registry->revokeIssuedBefore('someone', new \DateTimeImmutable('@2000'));

        $this->assertSame(2000, $registry->revokedBefore('someone'));
    }

    /**
     * The mark only has to outlive the JWTs issued before it, which is what the ttl is for.
     */
    public function test_gives_the_mark_the_configured_window(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(7200);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item);

        (new CacheJWTRevocationRegistry($pool, 7200))->revokeIssuedBefore('someone', new \DateTimeImmutable());
    }

    /**
     * The pool is the application's, and anything else found under the key is treated as no mark
     * rather than compared against as if it were one.
     */
    public function test_ignores_something_else_stored_under_the_key(): void
    {
        $registry = $this->registry();

        $registry->revokeIssuedBefore('someone', new \DateTimeImmutable('@1000'));

        $key = array_keys($this->pool->getValues())[0];
        $item = $this->pool->getItem($key);
        $item->set('not a timestamp');
        $this->pool->save($item);

        $this->assertNull($registry->revokedBefore('someone'));
    }

    /**
     * A user identifier is usually an email address, which PSR-6 reserves the `@` of, and cache keys
     * turn up in file names and profiler dumps — not somewhere to leave a list of your users.
     */
    public function test_does_not_use_the_identifier_itself_as_the_key(): void
    {
        $this->registry()->revokeIssuedBefore('someone@example.com', new \DateTimeImmutable());

        foreach (array_keys($this->pool->getValues()) as $key) {
            $this->assertStringNotContainsString('someone@example.com', $key);
        }
    }

    private function registry(): CacheJWTRevocationRegistry
    {
        return new CacheJWTRevocationRegistry($this->pool, 3600);
    }
}
