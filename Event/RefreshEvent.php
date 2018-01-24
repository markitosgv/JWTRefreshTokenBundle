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
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;

class RefreshEvent extends Event
{
    private $refreshToken;

    private $preAuthenticatedToken;

    public function __construct(RefreshTokenInterface $refreshToken, PreAuthenticatedToken $preAuthenticatedToken)
    {
        $this->refreshToken = $refreshToken;
        $this->preAuthenticatedToken = $preAuthenticatedToken;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function getPreAuthenticatedToken()
    {
        return $this->preAuthenticatedToken;
    }
}
