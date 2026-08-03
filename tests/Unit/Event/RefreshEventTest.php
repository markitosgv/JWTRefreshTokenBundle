<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Event;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class RefreshEventTest extends TestCase
{
    public function test_exposes_what_the_listeners_are_given_on_a_refresh(): void
    {
        $refreshToken = $this->createMock(RefreshTokenInterface::class);
        $token = $this->createMock(TokenInterface::class);

        $event = new RefreshEvent($refreshToken, $token, 'api');

        $this->assertSame($refreshToken, $event->getRefreshToken());
        $this->assertSame($token, $event->getToken());
        $this->assertSame('api', $event->getFirewallName());
    }

    public function test_has_no_firewall_name_when_none_is_given(): void
    {
        $event = new RefreshEvent(
            $this->createMock(RefreshTokenInterface::class),
            $this->createMock(TokenInterface::class)
        );

        $this->assertNull($event->getFirewallName());
    }
}
