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

/**
 * What is remembered about a refresh token that has already been spent.
 *
 * Deliberately not the token itself: the value is what a client would present, and the point of
 * keeping a record is to recognise a second presentation, not to have somewhere else the token can
 * leak from. The registry keys by a digest and keeps only this.
 *
 * @psalm-immutable
 */
final readonly class SpentRefreshToken
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        /**
         * The chain the spent token belonged to, or null if its class has no families. Without one
         * there is no chain to revoke, and only the user is left to act on.
         */
        public ?string $family,
        /**
         * Whose token it was, so that a reuse with no family to revoke can still be acted on.
         */
        public ?string $username,
    ) {
    }
}
