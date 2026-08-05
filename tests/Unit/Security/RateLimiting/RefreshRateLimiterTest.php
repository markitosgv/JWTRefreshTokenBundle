<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\RateLimiting;

use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Gesdinet\JWTRefreshTokenBundle\Security\RateLimiting\RefreshRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * A real limiter over in-memory storage rather than a mock: what matters is which requests end up
 * counted against each other, and a mock would only assert about the keys this class passes rather
 * than about the allowance they share.
 */
final class RefreshRateLimiterTest extends TestCase
{
    public function test_lets_a_request_within_the_allowance_through(): void
    {
        $this->expectNotToPerformAssertions();

        $this->limiterAllowing(3)->check($this->requestFrom('203.0.113.1'), 'a-token');
    }

    public function test_refuses_the_request_that_goes_over_the_allowance(): void
    {
        $limiter = $this->limiterAllowing(2);
        $request = $this->requestFrom('203.0.113.1');

        $limiter->check($request, 'a-token');
        $limiter->check($request, 'a-token');

        $this->expectException(TooManyRefreshRequestsException::class);

        $limiter->check($request, 'a-token');
    }

    /**
     * So the response can tell the caller when to come back rather than leaving them to poll, which
     * is more of what the limit is there to stop.
     */
    public function test_says_when_the_caller_may_try_again(): void
    {
        $limiter = $this->limiterAllowing(1);
        $request = $this->requestFrom('203.0.113.1');

        $limiter->check($request, 'a-token');

        try {
            $limiter->check($request, 'a-token');
            $this->fail('The second request should have been refused');
        } catch (TooManyRefreshRequestsException $refused) {
            $retryAfter = $refused->getRetryAfter();

            $this->assertNotNull($retryAfter);
            $this->assertGreaterThan(time(), $retryAfter->getTimestamp());
        }
    }

    public function test_counts_each_address_separately(): void
    {
        $this->expectNotToPerformAssertions();

        $limiter = $this->limiterAllowing(1);

        $limiter->check($this->requestFrom('203.0.113.1'), 'a-token');

        // A different caller, so a different allowance: one client must not be able to shut out
        // everybody else by exhausting theirs
        $limiter->check($this->requestFrom('203.0.113.2'), 'another-token');
    }

    /**
     * By address, one caller presenting many tokens is still one caller. This is the point of
     * keying by IP, and the reason it is the default.
     */
    public function test_counts_one_address_together_whatever_token_it_sends(): void
    {
        $limiter = $this->limiterAllowing(1);

        $limiter->check($this->requestFrom('203.0.113.1'), 'one-token');

        $this->expectException(TooManyRefreshRequestsException::class);

        $limiter->check($this->requestFrom('203.0.113.1'), 'a-completely-different-token');
    }

    public function test_counts_each_session_separately_when_keyed_by_token(): void
    {
        $this->expectNotToPerformAssertions();

        $limiter = $this->limiterAllowing(1, RefreshRateLimiter::BY_TOKEN);

        // The same address, so keying by IP would refuse the second
        $limiter->check($this->requestFrom('203.0.113.1'), 'one-session');
        $limiter->check($this->requestFrom('203.0.113.1'), 'another-session');
    }

    public function test_counts_one_session_together_when_keyed_by_token(): void
    {
        $limiter = $this->limiterAllowing(1, RefreshRateLimiter::BY_TOKEN);

        $limiter->check($this->requestFrom('203.0.113.1'), 'one-session');

        $this->expectException(TooManyRefreshRequestsException::class);

        // A different address, so keying by IP would let this through
        $limiter->check($this->requestFrom('203.0.113.9'), 'one-session');
    }

    /**
     * A request with no token would otherwise be the free way to hammer the endpoint, since there is
     * nothing to key it by.
     */
    public function test_counts_requests_carrying_no_token_at_all(): void
    {
        $limiter = $this->limiterAllowing(1, RefreshRateLimiter::BY_TOKEN);

        $limiter->check($this->requestFrom('203.0.113.1'), null);

        $this->expectException(TooManyRefreshRequestsException::class);

        $limiter->check($this->requestFrom('203.0.113.2'), null);
    }

    /**
     * A request that reaches the application without one, as happens behind a proxy that is not
     * trusted, still has to count against something.
     */
    public function test_counts_requests_with_no_client_address(): void
    {
        $limiter = $this->limiterAllowing(1);

        $limiter->check(new Request(), 'a-token');

        $this->expectException(TooManyRefreshRequestsException::class);

        $limiter->check(new Request(), 'another-token');
    }

    /**
     * The token reaches the limiter's storage, which is somewhere else it could be read from.
     */
    public function test_does_not_use_the_token_itself_as_the_limiter_key(): void
    {
        $seen = [];

        $factory = new class($seen) implements RateLimiterFactoryInterface {
            /**
             * @param list<string|null> $seen
             */
            public function __construct(public array &$seen)
            {
            }

            public function create(?string $key = null): FixedWindowLimiter
            {
                $this->seen[] = $key;

                return new FixedWindowLimiter((string) $key, 100, new \DateInterval('PT1M'), new InMemoryStorage());
            }
        };

        new RefreshRateLimiter($factory, RefreshRateLimiter::BY_TOKEN)
            ->check($this->requestFrom('203.0.113.1'), 'a-token-worth-stealing');

        $this->assertNotSame([], $seen);
        $this->assertNotContains('a-token-worth-stealing', $seen);
    }

    /**
     * @param positive-int                                           $requests
     * @param RefreshRateLimiter::BY_IP|RefreshRateLimiter::BY_TOKEN $key
     */
    private function limiterAllowing(int $requests, string $key = RefreshRateLimiter::BY_IP): RefreshRateLimiter
    {
        return new RefreshRateLimiter(
            new RateLimiterFactory(
                ['id' => 'refresh', 'policy' => 'fixed_window', 'limit' => $requests, 'interval' => '1 minute'],
                new InMemoryStorage()
            ),
            $key
        );
    }

    private function requestFrom(string $ip): Request
    {
        return Request::create('/token/refresh', 'POST', server: ['REMOTE_ADDR' => $ip]);
    }
}
