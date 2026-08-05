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

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A refresh token that had already been spent has been presented again.
 *
 * Two things cause this, and the bundle cannot tell them apart. One is a client racing itself: two
 * requests refreshing at once, the second arriving after the first spent the token. The other is
 * somebody using a copy they should not have. Because the second is the one that matters, the chain
 * is revoked either way, which is what turns a stolen token into a session that ends for both
 * parties rather than one that quietly continues for the attacker.
 *
 * That makes this event worth listening to. A racing client shows up as an occasional reuse for one
 * user; a theft usually does not look like that. Nothing else in the bundle is in a position to
 * judge, so the decision is left where the application's own signals are.
 */
final class RefreshTokenReuseDetectedEvent extends Event
{
    /**
     * @param int<0, max> $revokedTokens how many tokens of the chain were revoked in response
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly SpentRefreshToken $spentToken,
        private readonly int $revokedTokens,
        private readonly Request $request,
    ) {
    }

    /**
     * What was known about the token that was presented again: its chain and its owner.
     *
     * Not the token itself. Recognising a replay needs a digest, and keeping the value would only
     * put a live credential somewhere else it can be logged from.
     */
    public function getSpentToken(): SpentRefreshToken
    {
        return $this->spentToken;
    }

    /**
     * How many tokens were revoked in response.
     *
     * Zero means there was nothing to revoke rather than that nothing was done: a token class
     * without families leaves no chain to end, and a chain already revoked by an earlier reuse is
     * empty by the time the next one arrives.
     *
     * @return int<0, max>
     */
    public function getRevokedTokens(): int
    {
        return $this->revokedTokens;
    }

    /**
     * The request the spent token arrived on, for whatever the application logs about it.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
