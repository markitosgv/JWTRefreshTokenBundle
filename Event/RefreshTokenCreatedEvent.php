<?php

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

class RefreshTokenCreatedEvent extends Event
{
    /**
     * @var RefreshTokenInterface
     */
    private $refreshToken;

    public function __construct(RefreshTokenInterface $refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * @return RefreshTokenInterface
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }
}
