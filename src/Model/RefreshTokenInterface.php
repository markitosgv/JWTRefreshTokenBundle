<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use DateTimeInterface;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @psalm-mutable
 */
interface RefreshTokenInterface extends Stringable
{
    /**
     * Creates a new model instance based on the provided details.
     *
     * @psalm-impure the expiration date is derived from the current time
     */
    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): static;

    /**
     * @psalm-mutation-free
     */
    public function getId(): int|string|null;

    /**
     * @psalm-external-mutation-free
     */
    public function setRefreshToken(string $refreshToken): static;

    /**
     * @psalm-mutation-free
     */
    public function getRefreshToken(): ?string;

    /**
     * @psalm-external-mutation-free
     */
    public function setValid(DateTimeInterface $valid): static;

    /**
     * @psalm-mutation-free
     */
    public function getValid(): ?DateTimeInterface;

    /**
     * @psalm-external-mutation-free
     */
    public function setUsername(string $username): static;

    /**
     * @psalm-mutation-free
     */
    public function getUsername(): ?string;

    /**
     * @psalm-impure it compares against the current time
     */
    public function isValid(): bool;
}
