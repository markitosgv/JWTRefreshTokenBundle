<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Http\Authentication;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AuthenticationFailureHandlerTest extends TestCase
{
    private MockObject&EventDispatcherInterface $eventDispatcher;

    private AuthenticationFailureHandler $authenticationFailureHandler;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->authenticationFailureHandler = new AuthenticationFailureHandler($this->eventDispatcher);
    }

    public function test_dispatches_the_failure_event_and_returns_its_response(): void
    {
        $exception = new InvalidTokenException();

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RefreshAuthenticationFailureEvent::class), 'gesdinet.refresh_token_failure')
            ->willReturnArgument(0);

        $response = $this->authenticationFailureHandler->onAuthenticationFailure(new Request(), $exception);

        $this->assertInstanceOf(RefreshAuthenticationFailureResponse::class, $response);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString($exception->getMessageKey(), (string) $response->getContent());
    }

    /**
     * Being refused for asking too often is not a credentials problem, and 401 would tell a client
     * to go and get new ones — which is more requests, at an endpoint already saying it has had too
     * many.
     */
    public function test_answers_a_rate_limited_request_with_too_many_requests(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $response = $this->authenticationFailureHandler->onAuthenticationFailure(
            new Request(),
            (new TooManyRefreshRequestsException())->setRetryAfter(new \DateTimeImmutable('@'.(time() + 42)))
        );

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertEqualsWithDelta(42, (int) $response->headers->get('Retry-After'), 2);
    }

    /**
     * Retry-After is optional, and the response is still a 429 without it.
     */
    public function test_leaves_out_retry_after_when_nothing_said_when_to_come_back(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $response = $this->authenticationFailureHandler->onAuthenticationFailure(
            new Request(),
            new TooManyRefreshRequestsException()
        );

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Retry-After'));
    }

    /**
     * A window that has already passed by the time the response is built asks for zero rather than
     * a negative delay, which a client would have to guess at.
     */
    public function test_never_asks_the_caller_to_wait_a_negative_time(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatch')->willReturnArgument(0);

        $response = $this->authenticationFailureHandler->onAuthenticationFailure(
            new Request(),
            (new TooManyRefreshRequestsException())->setRetryAfter(new \DateTimeImmutable('@'.(time() - 60)))
        );

        $this->assertSame('0', $response->headers->get('Retry-After'));
    }

    public function test_returns_the_response_a_listener_replaced(): void
    {
        $replacement = new RefreshAuthenticationFailureResponse('Replaced by a listener');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (RefreshAuthenticationFailureEvent $event) use ($replacement): RefreshAuthenticationFailureEvent {
                $event->setResponse($replacement);

                return $event;
            });

        $response = $this->authenticationFailureHandler->onAuthenticationFailure(new Request(), new InvalidTokenException());

        $this->assertSame($replacement, $response);
    }
}
