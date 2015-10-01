<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

/**
 * Interface RefreshTokenInterface.
 */
interface RefreshTokenInterface
{
    /**
     * Get id.
     *
     * @return int
     */
    public function getId();

    /**
     * Set refreshToken.
     *
     * @param string $refreshToken
     *
     * @return self
     */
    public function setRefreshToken($refreshToken = null);

    /**
     * Get refreshToken.
     *
     * @return string
     */
    public function getRefreshToken();

    /**
     * Set valid.
     *
     * @param \DateTime $valid
     *
     * @return self
     */
    public function setValid($valid);

    /**
     * Get valid.
     *
     * @return \DateTime
     */
    public function getValid();

    /**
     * Set username.
     *
     * @param $username
     *
     * @return self
     */
    public function setUsername($username);

    /**
     * Get user.
     *
     * @return $username
     */
    public function getUsername();

    /**
     * Check if is a valid refresh token.
     *
     * @return bool
     */
    public function isValid();
}
