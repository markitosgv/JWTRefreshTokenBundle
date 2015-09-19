<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenInterface;

/**
 * User Refresh Token
 *
 * @ORM\Table("refresh_tokens")
 * @ORM\Entity()
 */
class UserRefreshToken implements UserRefreshTokenInterface
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="refresh_token", type="string", length=128)
     * @Assert\NotBlank()
     */
    private $refreshToken;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenInterface")
     * @Assert\NotBlank()
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="valid", type="datetime")
     * @Assert\NotBlank()
     */
    private $valid;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set refreshToken
     *
     * @param string $refreshToken
     * @return UserRefreshToken
     */
    public function setRefreshToken($refreshToken = null)
    {
        if (null == $refreshToken) {
            $this->refreshToken = bin2hex(openssl_random_pseudo_bytes(64));
        } else {
            $this->refreshToken = $refreshToken;
        }

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

    /**
     * Set valid
     *
     * @param \DateTime $valid
     * @return UserRefreshToken
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid
     *
     * @return \DateTime 
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Set user
     *
     * @param \CustomerBundle\Entity\Customer $user
     * @return UserRefreshToken
     */
    public function setUser($user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \CustomerBundle\Entity\Customer 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Check if is a valid refresh token
     *
     * @return boolean
     */
    public function isValid()
    {
        $datetime = new \DateTime();
        return ($this->valid >= $datetime) ? true : false;
    }
}
