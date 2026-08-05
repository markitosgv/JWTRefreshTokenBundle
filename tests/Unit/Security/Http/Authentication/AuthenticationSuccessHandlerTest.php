<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Http\Authentication;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The collaborators are built once in setUp(), so a test only sets expectations on the ones it
 * is about. The others stay mock objects without any.
 */
#[AllowMockObjectsWithoutExpectations]
final class AuthenticationSuccessHandlerTest extends TestCase
{
    private MockObject&AuthenticationSuccessHandlerInterface $decoratedHandler;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private AuthenticationSuccessHandler $authenticationSuccessHandler;

    protected function setUp(): void
    {
        $this->decoratedHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->authenticationSuccessHandler = new AuthenticationSuccessHandler(
            $this->decoratedHandler,
            $this->eventDispatcher
        );
    }

    public function test_dispatches_the_refresh_event_when_the_token_comes_from_a_refresh(): void
    {
        $request = new Request();
        $response = new Response();
        $firewallName = 'api';

        // A real one rather than a stub: the class is final, and it is a value object anyway
        $token = new PostRefreshTokenAuthenticationToken(
            $this->createStub(UserInterface::class),
            $firewallName,
            [],
            $this->createStub(RefreshTokenInterface::class)
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RefreshEvent::class), 'gesdinet.refresh_token')
            ->willReturnArgument(0);

        $this->decoratedHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($response);

        $this->authenticationSuccessHandler->setFirewallName($firewallName);

        $this->assertSame($response, $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token));
        $this->assertSame($firewallName, $this->authenticationSuccessHandler->getFirewallName());
    }

    public function test_does_not_dispatch_the_refresh_event_for_any_other_token(): void
    {
        $request = new Request();
        $response = new Response();
        $token = $this->createStub(TokenInterface::class);

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->decoratedHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($response);

        $this->assertSame($response, $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token));
    }

    public function test_passes_through_an_empty_response_from_the_decorated_handler(): void
    {
        $token = $this->createStub(TokenInterface::class);

        $this->decoratedHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->willReturn(null);

        $this->assertNull($this->authenticationSuccessHandler->onAuthenticationSuccess(new Request(), $token));
    }

    public function test_has_no_firewall_name_until_one_is_set(): void
    {
        $this->assertNull($this->authenticationSuccessHandler->getFirewallName());
    }
}
