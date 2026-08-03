<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Http\Authentication;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
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
