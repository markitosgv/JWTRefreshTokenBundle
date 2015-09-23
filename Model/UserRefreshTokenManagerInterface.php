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
 * Interface to be implemented by user managers. This adds an additional level
 * of abstraction between your application, and the actual repository.
 *
 * All changes to UserRefreshTokens should happen through this interface.
 */
interface UserRefreshTokenManagerInterface
{
    /**
     * Creates an empty user instance.
     *
     * @return UserRefreshTokenInterface
     */
    public function create();

    /**
     * @param string                    $refreshToken
     * @param UserRefreshTokenInterface $user
     *
     * @return UserRefreshTokenInterface
     */
    public function get($refreshToken, $user);

    /**
     * @param UserRefreshTokenInterface $user
     *
     * @return UserRefreshTokenInterface
     */
    public function getLastFromUser($user);

    /**
     * @param UserRefreshTokenInterface $userRefreshToken
     */
    public function save(UserRefreshTokenInterface $userRefreshToken);

    /**
     * @param UserRefreshTokenInterface $userRefreshToken
     */
    public function delete(UserRefreshTokenInterface $userRefreshToken);

    /**
     */
    public function revokeAllInvalid();

    /**
     * Returns the user's fully qualified class name.
     *
     * @return string
     */
    public function getClass();
}
