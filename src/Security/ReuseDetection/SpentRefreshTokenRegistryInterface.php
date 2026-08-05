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
     * Called with the token as it was before deletion, since that is the only moment its family and
     * its owner are both still to hand.
     *
     * @psalm-impure it writes to the store
     */
    public function remember(RefreshTokenInterface $refreshToken): void;

    /**
     * What was recorded about this token, or null if it was never spent.
     *
     * Null is the ordinary answer: most unknown tokens are simply wrong, not replayed.
     *
     * @psalm-impure it reads a store that anything may have written to
     */
    public function recall(string $refreshToken): ?SpentRefreshToken;
}
