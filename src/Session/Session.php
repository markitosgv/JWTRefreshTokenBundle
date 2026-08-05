<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Session;

/**
 * One chain of refreshes, as something to show a user and let them end.
 *
 * A session is not a token. With `single_use` the token a client holds is replaced on every refresh,
 * so a list of tokens is a list of moments rather than a list of sessions, and "end this one" would
 * name a token that has usually already been replaced. The chain is what persists across refreshes
 * and is therefore what a user recognises as "this browser, still signed in".
 *
 * @psalm-immutable
 */
final readonly class Session
{
    /**
     * @param positive-int $tokens
     *
     * @psalm-mutation-free
     */
    public function __construct(
        /**
         * The chain, which is what {@see SessionLister::end()} takes.
         *
         * Null for a token stored before this bundle had chains. Those are shown rather than hidden,
         * because a session a user cannot see is worse than one they cannot end, but there is
         * nothing linking them to anything and they cannot be ended individually. They go on their
         * own as their tokens expire.
         */
        public ?string $id,
        /**
         * When this session stops being refreshable, which is the last expiry among its tokens.
         *
         * Deliberately not an `isExpired()`: this object reads no clock, so what it says does not
         * depend on when it is asked. Compare it against your own idea of now.
         */
        public \DateTimeInterface $expiresAt,
        /**
         * When the chain itself runs out, whatever the tokens say, or null if `max_session_lifetime`
         * is not configured. A session with one is going to end on that date however much it is
         * used.
         */
        public ?\DateTimeInterface $endsAt,
        /**
         * How many tokens of this chain are still stored.
         *
         * Normally one — the client holds the newest and the rest were deleted as they were spent.
         * More than one means either `single_use` is off, or tokens were issued and never used.
         */
        public int $tokens,
        /**
         * Whether this is the session the request asking came from, so a screen can say "this
         * device" rather than inviting somebody to sign themselves out by accident.
         */
        public bool $current,
    ) {
    }
}
