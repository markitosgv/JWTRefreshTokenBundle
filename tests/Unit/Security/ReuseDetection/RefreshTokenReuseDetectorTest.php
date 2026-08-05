<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\ReuseDetection;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenReuseDetectedEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\RefreshTokenReuseDetector;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshTokenRegistryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AllowMockObjectsWithoutExpectations]
final class RefreshTokenReuseDetectorTest extends TestCase
{
    /**
     * The common case by far. A token nobody has a row for is usually just wrong, and ending a
     * session over one would sign users out for a typo.
     */
    public function test_does_nothing_for_a_token_that_was_never_spent(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(null);

        /** @var RefreshTokenManagerInterface&FamilyRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, FamilyRefreshTokenManagerInterface::class]);
        $manager->expects($this->never())->method('revokeFamily');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        (new RefreshTokenReuseDetector($registry, $manager, $dispatcher))
            ->unknownTokenPresented('a-token-nobody-ever-had', Request::create('/token/refresh', 'POST'));
    }

    /**
     * The point of the whole thing: the replay does not just fail, it takes the chain with it. The
     * attacker's copy stops working and the legitimate client is signed out, which is the part
     * somebody actually notices.
     */
    public function test_revokes_the_chain_a_replayed_token_belonged_to(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(new SpentRefreshToken('the-chain', 'someone'));

        /** @var RefreshTokenManagerInterface&FamilyRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, FamilyRefreshTokenManagerInterface::class]);
        $manager->expects($this->once())->method('revokeFamily')->with('the-chain')->willReturn(2);

        $event = $this->dispatchedEventFrom($registry, $manager);

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $event);
        $this->assertSame('the-chain', $event->getSpentToken()->family);
        $this->assertSame('someone', $event->getSpentToken()->username);
        $this->assertSame(2, $event->getRevokedTokens());
    }

    /**
     * A token class without families leaves no chain to end. The event still goes out: knowing a
     * token was replayed is worth having, and a listener holding the username can revoke by user.
     */
    public function test_reports_the_reuse_even_with_no_chain_to_revoke(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(new SpentRefreshToken(null, 'someone'));

        /** @var RefreshTokenManagerInterface&FamilyRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, FamilyRefreshTokenManagerInterface::class]);
        $manager->expects($this->never())->method('revokeFamily');

        $event = $this->dispatchedEventFrom($registry, $manager);

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $event);
        $this->assertNull($event->getSpentToken()->family);
        $this->assertSame(0, $event->getRevokedTokens(), 'Nothing was revoked, and that is not the same as nothing being wrong');
    }

    /**
     * A manager of an application's own may not be able to revoke by family, and cannot be made to.
     */
    public function test_reports_the_reuse_when_the_manager_cannot_revoke_a_chain(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(new SpentRefreshToken('the-chain', 'someone'));

        $event = $this->dispatchedEventFrom($registry, $this->createStub(RefreshTokenManagerInterface::class));

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $event);
        $this->assertSame(0, $event->getRevokedTokens());
    }

    /**
     * A chain already emptied by an earlier replay revokes nothing, which is reported as such
     * rather than as a negative count.
     */
    public function test_reports_nothing_revoked_for_a_chain_already_gone(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(new SpentRefreshToken('the-chain', 'someone'));

        /** @var RefreshTokenManagerInterface&FamilyRefreshTokenManagerInterface&MockObject $manager */
        $manager = $this->createMockForIntersectionOfInterfaces([RefreshTokenManagerInterface::class, FamilyRefreshTokenManagerInterface::class]);
        $manager->method('revokeFamily')->willReturn(0);

        $event = $this->dispatchedEventFrom($registry, $manager);

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $event);
        $this->assertSame(0, $event->getRevokedTokens());
    }

    public function test_hands_the_listener_the_request_the_replay_arrived_on(): void
    {
        $registry = $this->createStub(SpentRefreshTokenRegistryInterface::class);
        $registry->method('recall')->willReturn(new SpentRefreshToken(null, 'someone'));

        $request = Request::create('/token/refresh', 'POST');

        $event = $this->dispatchedEventFrom($registry, $this->createStub(RefreshTokenManagerInterface::class), $request);

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $event);
        $this->assertSame($request, $event->getRequest());
    }

    /**
     * Runs a replay through the detector on a real dispatcher and hands back what was dispatched.
     */
    private function dispatchedEventFrom(
        SpentRefreshTokenRegistryInterface $registry,
        RefreshTokenManagerInterface $manager,
        ?Request $request = null,
    ): ?RefreshTokenReuseDetectedEvent {
        $dispatched = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            'gesdinet.refresh_token_reuse_detected',
            static function (RefreshTokenReuseDetectedEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        (new RefreshTokenReuseDetector($registry, $manager, $dispatcher))
            ->unknownTokenPresented('a-replayed-token', $request ?? Request::create('/token/refresh', 'POST'));

        return $dispatched;
    }
}
