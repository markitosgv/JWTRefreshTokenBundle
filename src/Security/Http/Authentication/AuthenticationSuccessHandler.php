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

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private ?string $firewallName = null;

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly AuthenticationSuccessHandlerInterface $lexikAuthenticationSuccessHandler,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * The response is whatever the decorated handler returns, and
     * {@see AuthenticationSuccessHandlerInterface} allows that to be null.
     */
    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
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

    /**
     * @psalm-external-mutation-free
     */
    public function setFirewallName(string $firewallName): void
    {
        $this->firewallName = $firewallName;
    }
}
