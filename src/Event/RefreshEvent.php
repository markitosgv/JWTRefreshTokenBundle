<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class RefreshEvent extends Event
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly RefreshTokenInterface $refreshToken,
        private readonly TokenInterface $token,
        private readonly ?string $firewallName,
        private readonly Request $request,
    ) {
    }

    public function getRefreshToken(): RefreshTokenInterface
    {
        return $this->refreshToken;
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    public function getFirewallName(): ?string
    {
        return $this->firewallName;
    }

    /**
     * The request the refresh was made with.
     *
     * Whatever the client sent alongside the refresh token is here: the JWT being replaced, if it
     * sent one, and anything else a listener needs to act on. Without it a listener has to reach
     * for the request stack, which is the same request by a longer route.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
