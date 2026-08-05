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

namespace Gesdinet\JWTRefreshTokenBundle\Security\RateLimiting;

use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Bounds how often the refresh endpoint will answer.
 *
 * The endpoint trades a token for a JWT with no password in between, which makes it worth limiting
 * on its own terms rather than leaving it to whatever protects the login form.
 *
 * @internal
 */
final readonly class RefreshRateLimiter
{
    public const string BY_IP = 'ip';
    public const string BY_TOKEN = 'token';

    /**
     * @param self::BY_IP|self::BY_TOKEN $key
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private RateLimiterFactoryInterface $limiterFactory,
        private string $key = self::BY_IP,
    ) {
    }

    /**
     * Consumes one from the limiter, or refuses the request.
     *
     * Called before the token is looked up, so a caller learns nothing from how long the refusal
     * took about whether the token they sent exists.
     *
     * @throws TooManyRefreshRequestsException
     */
    public function check(Request $request, ?string $refreshToken): void
    {
        $limit = $this->limiterFactory->create($this->keyFor($request, $refreshToken))->consume();

        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRefreshRequestsException()->setRetryAfter($limit->getRetryAfter());
    }

    /**
     * What the requests are counted against.
     *
     * By IP the endpoint is protected as an endpoint: one caller cannot hammer it whatever tokens
     * they present. The cost is that everybody behind one address shares the allowance, which for a
     * mobile network or an office is a lot of people.
     *
     * By token each session gets its own allowance, so no legitimate client can be shut out by
     * another. That bounds how fast a session may refresh, and does nothing at all about a caller
     * arriving with a different token every time.
     */
    private function keyFor(Request $request, ?string $refreshToken): string
    {
        if (self::BY_TOKEN === $this->key) {
            // Digested for the same reason the spent-token registry digests: the key reaches the
            // limiter's storage, and a refresh token is a credential. A request with no token at
            // all still has to count against something, or it would be the free way to hammer this
            return null === $refreshToken ? 'no-token' : hash('sha256', $refreshToken);
        }

        return $request->getClientIp() ?? 'no-client-ip';
    }
}
