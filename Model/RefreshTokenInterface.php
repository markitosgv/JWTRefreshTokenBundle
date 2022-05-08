<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;

interface RefreshTokenInterface extends \Stringable
{
    /**
     * Creates a new model instance based on the provided details.
     */
    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): static;

    public function getId(): int|string|null;

    public function setRefreshToken(string $refreshToken): static;

    public function getRefreshToken(): ?string;

    public function setValid(\DateTimeInterface $valid): static;

    public function getValid(): ?\DateTimeInterface;

    public function setUsername(string $username): static;

    public function getUsername(): ?string;

    public function isValid(): bool;
}
