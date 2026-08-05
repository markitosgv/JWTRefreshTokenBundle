<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Cache;

use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps refresh tokens in a PSR-6 pool instead of a database.
 *
 * Refresh tokens have a natural expiry, which is the one thing a cache does without being asked. So
 * there is no `gesdinet:jwt:clear` to schedule and no table to watch grow — an expired token is gone
 * because the pool dropped it. For an application that does not need to list or revoke sessions,
 * that is a simpler deployment than a table.
 *
 * **What it deliberately cannot do.** A pool answers questions about a key you already hold and
 * nothing else: it cannot be asked which tokens belong to a user, or which have expired, because it
 * cannot be enumerated. The methods that need that say so rather than quietly answering wrongly,
 * and the interfaces those live behind — List, Revoke and Family — are not implemented at all, so a
 * feature needing one refuses at compile time rather than at three in the morning.
 *
 * That rules out `max_tokens_per_user`, `reuse_detection`'s chain revocation, listing a user's
 * sessions, and `gesdinet:jwt:revoke`. If you want those, use a database.
 *
 * The pool has to be shared between your processes and it has to be persistent. A local or
 * in-memory one signs everybody out on deploy, since losing the pool is losing every session.
 */
final readonly class CacheRefreshTokenManager implements RefreshTokenManagerInterface
{
    private const PREFIX = 'gesdinet_jwt_refresh_token_';

    /**
     * @param class-string<RefreshTokenInterface> $class
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
        private string $class,
    ) {
    }

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        $item = $this->pool->getItem(self::key($refreshToken));

        if (!$item->isHit()) {
            return null;
        }

        $stored = $item->get();

        // The pool belongs to the application and its contents are not this bundle's to trust
        return is_array($stored) ? $this->hydrate($stored) : null;
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $value = $refreshToken->getRefreshToken();

        if (null === $value) {
            throw new \InvalidArgumentException('Cannot save a refresh token without a token string.');
        }

        $valid = $refreshToken->getValid();

        $stored = [
            'refreshToken' => $value,
            'username' => $refreshToken->getUsername(),
            'valid' => $valid?->getTimestamp(),
        ];

        if ($refreshToken instanceof FamilyAwareRefreshTokenInterface) {
            $stored['family'] = $refreshToken->getFamily();
            $stored['familyValid'] = $refreshToken->getFamilyValid()?->getTimestamp();
        }

        $item = $this->pool->getItem(self::key($value));
        $item->set($stored);

        // The whole reason a cache suits this: the token goes when it expires, with nothing
        // scheduled to remove it. A token with no expiry at all is kept rather than made immortal by
        // a null the pool would read as "forever" — there is nothing sensible to invent here
        if (null !== $valid) {
            $item->expiresAt($valid);
        }

        $this->pool->save($item);
    }

    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        $value = $refreshToken->getRefreshToken();

        if (null === $value) {
            return 0;
        }

        $key = self::key($value);

        // PSR-6 reports deleting a key that was not there as a success, so what was actually removed
        // has to be read first. Two callers racing may both be told they deleted it, which is the
        // distinction single_use rotation relies on and one more thing a pool cannot give you
        if (!$this->pool->hasItem($key)) {
            return 0;
        }

        return $this->pool->deleteItem($key) ? 1 : 0;
    }

    /**
     * Nothing, because there is never anything to do.
     *
     * Every token is stored to expire when it expires, so the pool has already dropped the ones this
     * would look for. An empty list is the truthful answer rather than a stub: no expired token is
     * left behind, which is exactly what the caller wanted to know.
     *
     * @return RefreshTokenInterface[]
     *
     * @psalm-pure it genuinely does nothing, which is why there is nothing to be impure about
     */
    #[\Override]
    public function revokeAllInvalid(?\DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        return [];
    }

    /**
     * Nothing, for the same reason as {@see revokeAllInvalid()}.
     *
     * @return RefreshTokenInterface[]
     *
     * @psalm-pure it genuinely does nothing, which is why there is nothing to be impure about
     */
    #[\Override]
    public function revokeAllInvalidBatch(?\DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        return [];
    }

    /**
     * A pool cannot be asked which of its keys belong to a user.
     *
     * Answering null would read as "this user has no tokens", which is a different thing from "this
     * cannot be known" and is the answer a caller would go on to act on.
     *
     * @psalm-pure it reaches no storage; it only refuses
     */
    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        throw new \LogicException(sprintf('"%s" cannot look a token up by user: a PSR-6 pool answers for keys it is given and cannot be enumerated. Store the tokens in a database if you need this.', self::class));
    }

    /**
     * @return class-string<RefreshTokenInterface>
     *
     * @psalm-mutation-free
     */
    #[\Override]
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @param array<mixed, mixed> $stored
     */
    private function hydrate(array $stored): ?RefreshTokenInterface
    {
        $value = $stored['refreshToken'] ?? null;
        $username = $stored['username'] ?? null;
        $valid = $stored['valid'] ?? null;

        if (!is_string($value) || !is_string($username) || !is_int($valid)) {
            return null;
        }

        $class = $this->class;
        $token = new $class();
        $token->setRefreshToken($value);
        $token->setUsername($username);
        $token->setValid((new \DateTime())->setTimestamp($valid));

        $family = $stored['family'] ?? null;
        $familyValid = $stored['familyValid'] ?? null;

        if ($token instanceof FamilyAwareRefreshTokenInterface && is_string($family)) {
            $token->setFamily($family);

            if (is_int($familyValid)) {
                $token->setFamilyValid((new \DateTime())->setTimestamp($familyValid));
            }
        }

        return $token;
    }

    /**
     * The token is digested rather than used as the key.
     *
     * Cache keys turn up in file names, in `redis-cli KEYS` output and in profiler dumps, and a
     * refresh token is a credential. PSR-6 also reserves characters a token could contain.
     *
     * @psalm-pure
     */
    private static function key(string $refreshToken): string
    {
        return self::PREFIX.hash('sha256', $refreshToken);
    }
}
