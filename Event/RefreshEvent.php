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

class RefreshEvent extends Event
{
    /**
     * @var RefreshTokenInterface
     */
    private $refreshToken;

    /**
     * @var TokenInterface
     */
    private $token;

    /**
     * @var string|null
     */
    private $firewallName;

    public function __construct(RefreshTokenInterface $refreshToken, TokenInterface $token, ?string $firewallName = null)
    {
        $this->refreshToken = $refreshToken;
        $this->token = $token;
        $this->firewallName = $firewallName;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
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
