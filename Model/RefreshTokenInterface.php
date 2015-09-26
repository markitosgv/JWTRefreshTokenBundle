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
     * Set username.
     *
     * @param string $username
     *
     * @return self
     */
    public function setUsername($username);

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername();

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
     * Check if is a valid refresh token.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Renew refresh token.
     *
     * @return self
     */
    public function renewRefreshToken();
}
