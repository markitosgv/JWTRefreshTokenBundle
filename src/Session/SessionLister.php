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

use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The "where you are signed in" screen, and the button next to each row.
 *
 * Everyone building this reaches for `findAllForUser()` and lists the tokens, which is a list of
 * moments rather than of sessions: with `single_use` a token is replaced on every refresh, so the
 * same browser appears as a new row each time it refreshes and the row you revoke is usually one
 * that has already gone. Grouping by chain is what turns that into the thing a user recognises.
 */
final readonly class SessionLister
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private ListRefreshTokenManagerInterface&FamilyRefreshTokenManagerInterface $refreshTokenManager,
    ) {
    }

    /**
     * The user's sessions, the one lasting longest first.
     *
     * @param string|null $currentRefreshToken the token the request asking came with, when it came
     *                                         with one, so that its session can be marked
     *
     * @return list<Session>
     */
    public function forUser(UserInterface $user, ?string $currentRefreshToken = null): array
    {
        /** @var array<string, non-empty-list<RefreshTokenInterface>> $chains */
        $chains = [];

        // A token with no chain is its own session rather than one of a group: nothing says the
        // ones predating families came from the same login, so each goes in on its own
        /** @var list<non-empty-list<RefreshTokenInterface>> $loose */
        $loose = [];

        foreach ($this->refreshTokenManager->findAllForUser($user) as $token) {
            $family = $token instanceof FamilyAwareRefreshTokenInterface ? $token->getFamily() : null;

            if (null === $family) {
                $loose[] = [$token];

                continue;
            }

            $chains[$family][] = $token;
        }

        $sessions = [];

        foreach ($chains as $family => $tokens) {
            $sessions[] = $this->sessionFor((string) $family, $tokens, $currentRefreshToken);
        }

        foreach ($loose as $tokens) {
            $sessions[] = $this->sessionFor(null, $tokens, $currentRefreshToken);
        }

        usort($sessions, static fn (Session $a, Session $b): int => $b->expiresAt <=> $a->expiresAt);

        return $sessions;
    }

    /**
     * Ends one of the user's sessions, revoking every token of that chain.
     *
     * The session is checked to be this user's first. A chain is addressed by an identifier the
     * client hands back, so without that check anybody who learnt or guessed one could end a
     * stranger's session — and a screen listing sessions is exactly where such an identifier is
     * handed out. A session that is not theirs and one that does not exist give the same answer on
     * purpose, so the call cannot be used to find out which chains exist.
     *
     * @return int the number of revoked refresh tokens, zero when there was no such session of this
     *             user's
     */
    public function end(UserInterface $user, string $sessionId): int
    {
        foreach ($this->refreshTokenManager->findAllForUser($user) as $token) {
            if ($token instanceof FamilyAwareRefreshTokenInterface && $token->getFamily() === $sessionId) {
                return $this->refreshTokenManager->revokeFamily($sessionId);
            }
        }

        return 0;
    }

    /**
     * @param non-empty-list<RefreshTokenInterface> $tokens
     */
    private function sessionFor(?string $family, array $tokens, ?string $currentRefreshToken): Session
    {
        $expiresAt = null;
        $endsAt = null;
        $current = false;

        foreach ($tokens as $token) {
            $valid = $token->getValid();

            if (null !== $valid && (null === $expiresAt || $valid > $expiresAt)) {
                $expiresAt = $valid;
            }

            if ($token instanceof FamilyAwareRefreshTokenInterface && null === $endsAt) {
                $endsAt = $token->getFamilyValid();
            }

            if (null !== $currentRefreshToken && $token->getRefreshToken() === $currentRefreshToken) {
                $current = true;
            }
        }

        return new Session(
            $family,
            // A token with no expiry at all should not exist; treating it as already over beats
            // showing a session that appears to last forever
            $expiresAt ?? new \DateTimeImmutable('@0'),
            $endsAt,
            \count($tokens),
            $current,
        );
    }
}
