<?php

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

abstract class UserRefreshToken extends BaseUser
{
    /**
     * @var string
     *
     * @ORM\Column(name="refresh_token", type="string", length=128)
     */
    protected $refreshToken;

    /**
     * Set refreshToken
     *
     * @param string $refreshToken
     * @return UserRefreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get refreshToken
     *
     * @return string 
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }
}
