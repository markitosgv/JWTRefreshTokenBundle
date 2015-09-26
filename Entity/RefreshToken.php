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
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * User Refresh Token.
 *
 * @ORM\Table("refresh_tokens")
 * @ORM\Entity(repositoryClass="Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository")
 */
class RefreshToken implements RefreshTokenInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255)
     * @Assert\NotBlank()
     */
    private $username;

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
     * @ORM\Column(name="valid", type="datetime")
     * @Assert\NotBlank()
     */
    private $valid;

    public function constructor()
    {
        $this->refreshToken = bin2hex(openssl_random_pseudo_bytes(64));
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set username.
     *
     * @param string $username
     *
     * @return RefreshToken
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set refreshToken.
     *
     * @param string $refreshToken
     *
     * @return RefreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get refreshToken.
     *
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Set valid.
     *
     * @param \DateTime $valid
     *
     * @return RefreshToken
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid.
     *
     * @return \DateTime
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Check if is a valid refresh token.
     *
     * @return bool
     */
    public function isValid()
    {
        $datetime = new \DateTime();

        return ($this->valid >= $datetime) ? true : false;
    }

    /**
     * Renew refresh token.
     *
     * @return self
     */
    public function renewRefreshToken()
    {
        $this->refreshToken = bin2hex(openssl_random_pseudo_bytes(64));
    }
}
