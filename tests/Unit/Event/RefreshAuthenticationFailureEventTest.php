<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Event;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class RefreshAuthenticationFailureEventTest extends TestCase
{
    public function test_carries_the_exception_and_the_response_to_the_listeners(): void
    {
        $exception = new InvalidTokenException();
        $response = new Response();

        $event = new RefreshAuthenticationFailureEvent($exception, $response);

        $this->assertSame($exception, $event->getException());
        $this->assertSame($response, $event->getResponse());
    }

    public function test_lets_a_listener_replace_the_response(): void
    {
        $event = new RefreshAuthenticationFailureEvent(new InvalidTokenException(), new Response());
        $replacement = new Response();

        $event->setResponse($replacement);

        $this->assertSame($replacement, $event->getResponse());
    }
}
