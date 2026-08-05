<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection;

use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps spent tokens in a PSR-6 pool.
 *
 * A cache is the right shape for this: every record is only worth keeping for as long as a replay
 * could still arrive, and expiry is what a cache does without being asked. Nothing here needs to
 * survive a restart — losing the records loses detections, not sessions.
 *
 * The pool has to be shared between your processes, for the same reason the blocklist does. A local
 * one means a replay is only caught when it happens to reach the machine that issued the token.
 */
final readonly class CacheSpentRefreshTokenRegistry implements SpentRefreshTokenRegistryInterface
{
    /**
     * @param positive-int $ttl how long a spent token stays recognisable, in seconds
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
        private int $ttl,
    ) {
    }

    #[\Override]
    public function remember(RefreshTokenInterface $refreshToken): void
    {
        $value = $refreshToken->getRefreshToken();

        if (null === $value) {
            return;
        }

        $item = $this->pool->getItem(self::key($value));

        $item->set(new SpentRefreshToken(
            $refreshToken instanceof FamilyAwareRefreshTokenInterface ? $refreshToken->getFamily() : null,
            $refreshToken->getUsername(),
        ));
        $item->expiresAfter($this->ttl);

        $this->pool->save($item);
    }

    #[\Override]
    public function recall(string $refreshToken): ?SpentRefreshToken
    {
        $item = $this->pool->getItem(self::key($refreshToken));

        if (!$item->isHit()) {
            return null;
        }

        $spent = $item->get();

        // A pool is shared storage and its contents are not this bundle's to trust; anything else
        // under the key is treated as nothing having been recorded
        return $spent instanceof SpentRefreshToken ? $spent : null;
    }

    /**
     * The token is digested rather than used as the key.
     *
     * PSR-6 reserves characters a token could contain, but the reason is the other one: cache keys
     * turn up in file names, in `redis-cli KEYS` output and in profiler dumps, and a refresh token
     * is a credential. A digest is enough to recognise the same value coming back.
     *
     * @psalm-pure
     */
    private static function key(string $refreshToken): string
    {
        return 'gesdinet_jwt_refresh_token_spent_'.hash('sha256', $refreshToken);
    }
}
