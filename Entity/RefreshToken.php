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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Refresh Token.
 *
 * @ORM\Table("refresh_tokens")
 * @ORM\Entity(repositoryClass="Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository")
 * @UniqueEntity("refreshToken")
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
     * @ORM\Column(name="refresh_token", type="string", length=128, unique=true)
     * @Assert\NotBlank()
     */
    private $refreshToken;

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
     * @ORM\Column(name="valid", type="datetime")
     * @Assert\NotBlank()
     */
    private $valid;

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
     * Set refreshToken.
     *
     * @param string $refreshToken
     *
     * @return RefreshToken
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
     * @return string Refresh Token
     */
    public function __toString()
    {
        return $this->getRefreshToken();
    }
}
