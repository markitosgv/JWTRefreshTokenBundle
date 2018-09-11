<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints as Assert;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * Abstract Refresh Token.
 *
 * @Unique("refreshToken")
 */
abstract class AbstractRefreshToken implements RefreshTokenInterface
{
    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    private $refreshToken;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    private $username;

    /**
     * @var \DateTime
     *
     * @Assert\NotBlank()
     */
    private $valid;

    /**
     * {@inheritdoc}
     */
    abstract public function getId();

    /**
     * Set refreshToken.
     *
     * @param string $refreshToken
     *
     * @return AbstractRefreshToken
     */
    public function setRefreshToken($refreshToken = null)
    {
        $this->refreshToken = null === $refreshToken
            ? bin2hex(openssl_random_pseudo_bytes(64))
            : $refreshToken;

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
     * @return AbstractRefreshToken
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
     * @return AbstractRefreshToken
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
        return $this->valid >= new \DateTime();
    }

    /**
     * @return string Refresh Token
     */
    public function __toString()
    {
        return $this->getRefreshToken();
    }
}
