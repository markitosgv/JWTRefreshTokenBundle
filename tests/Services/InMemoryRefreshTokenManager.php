<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Services;

use DateTime;
use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

/**
 * A manager storing the tokens in an array, so it needs none of Doctrine.
 *
 * It is what `refresh_token_manager` is for: an application keeping its tokens somewhere the
 * bundle knows nothing about, a PDO repository or a cache, names a service like this one and
 * nothing Doctrine is wired. Running it through the same contract as the three shipped managers is
 * what makes the interface implementable outside them a promise rather than an intention.
 */
final class InMemoryRefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * @var array<string, RefreshTokenInterface>
     */
    private array $tokens = [];

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        return $this->tokens[$refreshToken] ?? null;
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        $last = null;

        foreach ($this->tokens as $token) {
            if ($token->getUsername() !== $username) {
                continue;
            }

            if (null === $last || $this->expiry($token) > $this->expiry($last)) {
                $last = $token;
            }
        }

        return $last;
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken): void
    {
        $this->tokens[(string) $refreshToken->getRefreshToken()] = $refreshToken;
    }

    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        $key = (string) $refreshToken->getRefreshToken();

        if (!isset($this->tokens[$key])) {
            return 0;
        }

        unset($this->tokens[$key]);

        return 1;
    }

    #[\Override]
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        $datetime ??= new DateTime();
        $revoked = [];

        foreach ($this->tokens as $key => $token) {
            if ($this->expiry($token) >= $datetime->getTimestamp()) {
                continue;
            }

            $revoked[] = $token;
            unset($this->tokens[$key]);
        }

        return $revoked;
    }

    #[\Override]
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        $batchSize ??= self::DEFAULT_BATCH_SIZE;
        $datetime ??= new DateTime();
        $revoked = [];

        // Each batch is removed before the next is read, so the offset stays where it is, which is
        // why it goes unused here
        do {
            $batch = [];

            foreach ($this->tokens as $key => $token) {
                if (count($batch) >= $batchSize) {
                    break;
                }

                if ($this->expiry($token) >= $datetime->getTimestamp()) {
                    continue;
                }

                $batch[] = $token;
                unset($this->tokens[$key]);
            }

            foreach ($batch as $token) {
                $revoked[] = $token;
            }
        } while ([] !== $batch);

        return $revoked;
    }

    #[\Override]
    public function getClass(): string
    {
        return RefreshToken::class;
    }

    private function expiry(RefreshTokenInterface $token): int
    {
        return $token->getValid()?->getTimestamp() ?? 0;
    }
}
