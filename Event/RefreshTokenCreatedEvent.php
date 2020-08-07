<?php

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

class RefreshTokenCreatedEvent extends Event
{
    private $refreshToken;

    public function __construct(RefreshTokenInterface $refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }
}
