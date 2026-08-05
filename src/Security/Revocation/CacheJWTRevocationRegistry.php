<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Revocation;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps the revocation marks in a PSR-6 pool.
 *
 * A mark is only worth keeping until the last JWT that predates it would have expired anyway, which
 * is what the ttl is for and why a cache is the right shape. It is read on every authenticated
 * request, so the pool wants to be a fast one, and it has to be shared between your processes: a
 * local pool means a revoked user stays signed in everywhere the mark did not reach.
 */
final readonly class CacheJWTRevocationRegistry implements JWTRevocationRegistryInterface
{
    /**
     * @param positive-int $ttl how long a mark is kept, in seconds
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
        private int $ttl,
    ) {
    }

    #[\Override]
    public function revokeIssuedBefore(string $username, \DateTimeInterface $at): void
    {
        $item = $this->pool->getItem(self::key($username));

        $item->set($at->getTimestamp());
        $item->expiresAfter($this->ttl);

        $this->pool->save($item);
    }

    #[\Override]
    public function revokedBefore(string $username): ?int
    {
        $item = $this->pool->getItem(self::key($username));

        if (!$item->isHit()) {
            return null;
        }

        $at = $item->get();

        // The pool is the application's and its contents are not this bundle's to trust
        return is_int($at) ? $at : null;
    }

    /**
     * The identifier is digested rather than used as the key.
     *
     * PSR-6 reserves characters a user identifier may well contain — an email address has an `@` —
     * and cache keys turn up in file names and in profiler dumps, which is not somewhere to put a
     * list of your users.
     *
     * @psalm-pure
     */
    private static function key(string $username): string
    {
        return 'gesdinet_jwt_refresh_token_revoked_before_'.hash('sha256', $username);
    }
}
