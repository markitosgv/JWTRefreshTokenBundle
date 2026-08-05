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

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\JWTRevocationRegistryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;

/**
 * Refuses a JWT that was issued before its owner's sessions were revoked.
 *
 * A JWT is checked against its signature and its expiry with nothing consulted in between, which is
 * the point of them and the reason revoking a user's refresh tokens leaves their existing JWTs
 * working for up to the JWT ttl. Lexik's blocklist cannot help: it is keyed by `jti`, so it withdraws
 * a token you are holding, and the tokens that need withdrawing here are in clients.
 *
 * Comparing `iat` against a per-user mark withdraws all of them at once without needing to know what
 * they were.
 *
 * @internal
 */
final readonly class RejectJWTsIssuedBeforeRevocationListener
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private JWTRevocationRegistryInterface $registry,
        /**
         * The payload claim carrying the user, which is Lexik's `user_id_claim`.
         */
        private string $userClaim = 'username',
    ) {
    }

    public function __invoke(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        $username = $payload[$this->userClaim] ?? null;
        $issuedAt = $payload['iat'] ?? null;

        // A payload without both is one this cannot judge. Refusing it would break every token that
        // simply carries different claims, so it is left to the rest of the verification, which is
        // what was deciding before this listener existed
        if (!is_string($username) || !is_int($issuedAt)) {
            return;
        }

        $revokedBefore = $this->registry->revokedBefore($username);

        if (null === $revokedBefore) {
            return;
        }

        // Not `<`: a JWT issued in the same second as the revocation may have been issued just
        // before it, and the second-resolution `iat` cannot say which. Refusing it costs its holder
        // one round trip through the login they are about to be sent to anyway
        if ($issuedAt <= $revokedBefore) {
            $event->markAsInvalid();
        }
    }
}
