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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

class RefreshEvent extends Event
{
    private RefreshTokenInterface $refreshToken;

    private TokenInterface $token;

    private ?string $firewallName;

    public function __construct(RefreshTokenInterface $refreshToken, TokenInterface $token, ?string $firewallName = null)
    {
        $this->refreshToken = $refreshToken;
        $this->token = $token;
        $this->firewallName = $firewallName;
    }

    /**
     * @return RefreshTokenInterface
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @return TokenInterface
     *
     * @deprecated use getToken() instead
     */
    public function getPreAuthenticatedToken()
    {
        return $this->getToken();
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    public function getFirewallName(): ?string
    {
        return $this->firewallName;
    }
}
