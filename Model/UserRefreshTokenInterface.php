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
 * Interface UserRefreshTokenInterface.
 */
interface UserRefreshTokenInterface
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
     * Set user.
     *
     * @param $user
     *
     * @return self
     */
    public function setUser($user = null);

    /**
     * Get user.
     *
     * @return $user
     */
    public function getUser();

    /**
     * Check if is a valid refresh token.
     *
     * @return bool
     */
    public function isValid();
}
