<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Event;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class RefreshTokenNotFoundEventTest extends TestCase
{
    public function test_carries_the_exception_and_the_response_to_the_listeners(): void
    {
        $exception = new MissingTokenException();
        $response = new Response();

        $event = new RefreshTokenNotFoundEvent($exception, $response);

        $this->assertSame($exception, $event->getException());
        $this->assertSame($response, $event->getResponse());
    }

    /**
     * A listener may drop the response entirely, which is why the entry point falls back to the one
     * it built itself.
     */
    public function test_lets_a_listener_clear_the_response(): void
    {
        $event = new RefreshTokenNotFoundEvent(new MissingTokenException(), new Response());

        $event->setResponse();

        $this->assertNull($event->getResponse());
    }
}
