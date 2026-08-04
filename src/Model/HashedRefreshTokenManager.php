<?php

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use DateTimeInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Stores the hash of a refresh token rather than the token itself.
 *
 * A refresh token gets its holder back into an account without a password, so a copy of the
 * database is a copy of everybody's credentials. Storing the hash means a leaked table cannot be
 * used: the value it holds is not the one the endpoint accepts.
 *
 * SHA-256 rather than something slow. The reason passwords need bcrypt is that they are guessable,
 * and a token of 64 random bytes is not, so there is nothing for a slow hash to buy. It also has to
 * be deterministic: a refresh request carries the token and nothing else, so the row has to be
 * findable from it, which rules out a per-row salt.
 *
 * The stored value carries its algorithm, so a row can say which it is. That is what lets tokens
 * issued before this was turned on keep working: they are looked up as they are, and rewritten
 * hashed the first time they are used.
 */
final readonly class HashedRefreshTokenManager implements ListRefreshTokenManagerInterface, RefreshTokenManagerInterface, RevokeRefreshTokenManagerInterface
{
    private const PREFIX = 'sha256$';

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private RefreshTokenManagerInterface $manager,
        /**
         * Whether a token stored before hashing was turned on is still accepted, and rewritten
         * hashed when it is used.
         */
        private bool $acceptStoredInTheClear = true,
    ) {
    }

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        $hashed = $this->manager->get(self::hash($refreshToken));

        if (null !== $hashed) {
            return $hashed;
        }

        if (!$this->acceptStoredInTheClear) {
            return null;
        }

        // Issued before hashing was turned on. Taking it now and rewriting it hashed is what keeps
        // the change from signing everybody out
        $inTheClear = $this->manager->get($refreshToken);

        if (null === $inTheClear) {
            return null;
        }

        $this->save($inTheClear);

        return $inTheClear;
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken): void
    {
        $value = (string) $refreshToken->getRefreshToken();

        // Already hashed when a token read back from storage is saved again, as an updated
        // expiry does
        if (!self::isHashed($value)) {
            $refreshToken->setRefreshToken(self::hash($value));
        }

        $this->manager->save($refreshToken);
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        return $this->manager->getLastFromUsername($username);
    }

    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        return $this->manager->delete($refreshToken, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        return $this->manager->revokeAllInvalid($datetime, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        return $this->manager->revokeAllInvalidBatch($datetime, $batchSize, $offset, $andFlush);
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function getClass(): string
    {
        return $this->manager->getClass();
    }

    #[\Override]
    public function revokeAllForUser(UserInterface $user): int
    {
        return $this->delegateOrFail(RevokeRefreshTokenManagerInterface::class)->revokeAllForUser($user);
    }

    #[\Override]
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int
    {
        return $this->delegateOrFail(RevokeRefreshTokenManagerInterface::class)->revokeAllButNewestForUser($user, $keep);
    }

    #[\Override]
    public function findAllForUser(UserInterface $user): array
    {
        return $this->delegateOrFail(ListRefreshTokenManagerInterface::class)->findAllForUser($user);
    }

    /**
     * The manager underneath may implement only RefreshTokenManagerInterface, which this one cannot
     * make up for. Saying so beats a call to a method that is not there.
     *
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     *
     * @psalm-mutation-free
     */
    private function delegateOrFail(string $interface): object
    {
        if (!$this->manager instanceof $interface) {
            throw new \LogicException(sprintf('The refresh token manager being hashed, "%s", does not implement "%s".', get_debug_type($this->manager), $interface));
        }

        return $this->manager;
    }

    /**
     * @psalm-pure
     */
    private static function hash(string $refreshToken): string
    {
        return self::PREFIX.hash('sha256', $refreshToken);
    }

    /**
     * @psalm-pure
     */
    private static function isHashed(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
