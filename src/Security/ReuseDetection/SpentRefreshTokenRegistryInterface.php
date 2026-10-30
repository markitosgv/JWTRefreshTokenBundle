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

namespace Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * Remembers, for a while, which refresh tokens have been spent.
 *
 * With `single_use` a spent token is deleted, so presenting it again is indistinguishable from
 * presenting a token that never existed: both are "unknown". That is the one thing worth telling
 * apart, because a token that was spent and has come back means either two requests raced or
 * somebody is holding a copy they should not have.
 *
 * @psalm-mutable
 */
interface SpentRefreshTokenRegistryInterface
{
    /**
     * Records that this token has been spent.
     *
     * Two values are needed and they are not always the same one. `$presentedToken` is what the
     * client sent, and it is what a replay will arrive with, so it is what the entry is keyed on.
     * `$refreshToken` is the model, read before deletion because that is the only moment its family
     * and its owner are both still to hand.
     *
     * They differ whenever the manager underneath stores something other than the token: with
     * `hash_tokens` on, the model carries the hash. Keying on the model would key on the hash while
     * every lookup keys on the raw value, and nothing would ever be recalled.
     *
     * @psalm-impure it writes to the store
     */
    public function remember(string $presentedToken, RefreshTokenInterface $refreshToken): void;

    /**
     * What was recorded about this token, or null if it was never spent.
     *
     * Null is the ordinary answer: most unknown tokens are simply wrong, not replayed.
     *
     * @psalm-impure it reads a store that anything may have written to
     */
    public function recall(string $refreshToken): ?SpentRefreshToken;
}
