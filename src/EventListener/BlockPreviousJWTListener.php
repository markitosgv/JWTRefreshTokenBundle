<?php

declare(strict_types=1);

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingClaimException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;

/**
 * Blocks the JWT that was replaced, when the refresh request carried one.
 *
 * A JWT is verified by its signature and its expiry with nothing consulted in between, so until
 * LexikJWTAuthenticationBundle 3 there was no way to withdraw one and refreshing left the previous
 * JWT usable for the rest of its lifetime. Its blocklist is what makes this possible.
 *
 * Two cases are deliberately left alone:
 *
 * A request that carries no JWT has nothing to block. Refreshing does not require one, and a client
 * that discards its JWT before refreshing is the common shape.
 *
 * A JWT that no longer parses is left alone as well, which for an expired one is the point: it is
 * already refused everywhere, so blocking it buys nothing and only fills the store. What this
 * catches is the JWT that is still valid — a client refreshing before expiry, which is what
 * `return_expiration` encourages — where the old one would otherwise keep working.
 */
final readonly class BlockPreviousJWTListener
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private BlockedTokenManagerInterface $blockedTokenManager,
        private TokenExtractorInterface $tokenExtractor,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function __invoke(RefreshEvent $event): void
    {
        $jwt = $this->tokenExtractor->extract($event->getRequest());

        if (!is_string($jwt) || '' === $jwt) {
            return;
        }

        try {
            $payload = $this->jwtManager->parse($jwt);
        } catch (JWTDecodeFailureException) {
            return;
        }

        try {
            $this->blockedTokenManager->add($payload);
        } catch (MissingClaimException) {
            // Without the claims the blocklist keys on there is nothing to record it under
        }
    }
}
