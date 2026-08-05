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

/**
 * Remembers, per user, the moment their access was withdrawn.
 *
 * Lexik's blocklist is keyed by `jti`, so it can only withdraw a JWT somebody is holding in their
 * hand. Revoking a user's sessions has to withdraw every JWT already issued to them, and those are
 * not to hand — they are in clients. What can be recorded instead is when it happened, and a JWT
 * issued before that moment is refused on sight.
 *
 * @psalm-mutable
 */
interface JWTRevocationRegistryInterface
{
    /**
     * Records that every JWT issued to this user before now is no longer to be accepted.
     *
     * @psalm-impure it writes to the store
     */
    public function revokeIssuedBefore(string $username, \DateTimeInterface $at): void;

    /**
     * The moment this user's access was last withdrawn, as a timestamp, or null if it never was.
     *
     * Null is the ordinary answer and is read on every authenticated request, which is why the store
     * needs to be one that answers quickly.
     *
     * @psalm-impure it reads a store anything may have written to
     */
    public function revokedBefore(string $username): ?int;
}
