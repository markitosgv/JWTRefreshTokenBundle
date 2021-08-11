<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private AuthenticationSuccessHandlerInterface $lexikAuthenticationSuccessHandler;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var string|null
     */
    protected $firewallName;

    public function __construct(
        AuthenticationSuccessHandlerInterface $lexikAuthenticationSuccessHandler,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->lexikAuthenticationSuccessHandler = $lexikAuthenticationSuccessHandler;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        if ($token instanceof PostRefreshTokenAuthenticationToken) {
            $event = new RefreshEvent($token->getRefreshToken(), $token, $this->firewallName);

            $this->eventDispatcher->dispatch($event, 'gesdinet.refresh_token');
        }

        return $this->lexikAuthenticationSuccessHandler->onAuthenticationSuccess($request, $token);
    }

    public function getFirewallName(): ?string
    {
        return $this->firewallName;
    }

    public function setFirewallName(string $firewallName): void
    {
        $this->firewallName = $firewallName;
    }
}
