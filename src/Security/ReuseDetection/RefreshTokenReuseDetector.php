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

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenReuseDetectedEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Decides what a token nobody has heard of means.
 *
 * Rotation on its own — which is what `single_use` gives you — leaves a stolen token working until
 * the real user happens to refresh, and even then the theft goes unnoticed by everyone. Recognising
 * the replay is the other half: the chain ends, so the attacker's copy stops working and the
 * legitimate client is signed out, which is the part the user actually notices.
 *
 * @internal
 */
final readonly class RefreshTokenReuseDetector
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private SpentRefreshTokenRegistryInterface $registry,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Called with a token the storage has no row for.
     *
     * Most of the time that is simply a wrong token and nothing happens. The interesting case is the
     * one where it is a token that was spent.
     */
    public function unknownTokenPresented(string $refreshToken, Request $request): void
    {
        $spent = $this->registry->recall($refreshToken);

        if (null === $spent) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new RefreshTokenReuseDetectedEvent($spent, $this->revokeTheChain($spent), $request),
            'gesdinet.refresh_token_reuse_detected'
        );
    }

    /**
     * @return int<0, max>
     */
    private function revokeTheChain(SpentRefreshToken $spent): int
    {
        // A token class without families leaves nothing to revoke, and a manager that cannot revoke
        // by family cannot be made to. The event still goes out: knowing a token was replayed is
        // worth having even where the bundle cannot act on it, and a listener with the username
        // can revoke by user instead
        if (null === $spent->family || !$this->refreshTokenManager instanceof FamilyRefreshTokenManagerInterface) {
            return 0;
        }

        return max(0, $this->refreshTokenManager->revokeFamily($spent->family));
    }
}
