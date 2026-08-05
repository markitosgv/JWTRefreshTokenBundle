<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Event;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The collaborators are built once in setUp(), so a test only sets expectations on the ones it
 * is about. The others stay mock objects without any.
 */
#[AllowMockObjectsWithoutExpectations]
final class RefreshEventTest extends TestCase
{
    public function test_exposes_what_the_listeners_are_given_on_a_refresh(): void
    {
        $refreshToken = $this->createStub(RefreshTokenInterface::class);
        $token = $this->createStub(TokenInterface::class);

        $request = Request::create('/api/token/refresh', 'POST');

        $event = new RefreshEvent($refreshToken, $token, 'api', $request);

        $this->assertSame($refreshToken, $event->getRefreshToken());
        $this->assertSame($token, $event->getToken());
        $this->assertSame('api', $event->getFirewallName());
        $this->assertSame($request, $event->getRequest(), 'The request the refresh was made with, so a listener need not go looking for it');
    }

    public function test_has_no_firewall_name_when_none_is_given(): void
    {
        $event = new RefreshEvent(
            $this->createStub(RefreshTokenInterface::class),
            $this->createStub(TokenInterface::class),
            null,
            Request::create('/api/token/refresh', 'POST')
        );

        $this->assertNull($event->getFirewallName());
    }
}
