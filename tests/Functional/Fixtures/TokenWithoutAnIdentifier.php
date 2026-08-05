<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A token model with no identifier of its own.
 *
 * `RefreshTokenInterface` does not ask for one, and the DBAL backend fills an `id` property when
 * the model happens to have it. This is the model that does not, which is the branch that would
 * otherwise go unexercised.
 */
final class TokenWithoutAnIdentifier implements RefreshTokenInterface
{
    private ?string $refreshToken = null;
    private ?string $username = null;
    private ?DateTimeInterface $valid = null;

    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): static
    {
        $valid = new \DateTime();
        $valid->modify(sprintf('+%d seconds', $ttl));

        $token = new static();
        $token->setRefreshToken($refreshToken);
        $token->setUsername($user->getUserIdentifier());
        $token->setValid($valid);

        return $token;
    }

    public function getId(): int|string|null
    {
        return null;
    }

    public function setRefreshToken(string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setValid(DateTimeInterface $valid): static
    {
        $this->valid = $valid;

        return $this;
    }

    public function getValid(): ?DateTimeInterface
    {
        return $this->valid;
    }

    public function isValid(): bool
    {
        return null !== $this->valid && $this->valid >= new \DateTime();
    }

    public function __toString(): string
    {
        return (string) $this->refreshToken;
    }
}
