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

abstract class UserRefreshTokenManager implements UserRefreshTokenManagerInterface
{
    /**
     * Creates an empty UserRefreshToken instance.
     *
     * @return UserRefreshTokenInterface
     */
    public function create()
    {
        $class = $this->getClass();
        $user = new $class();

        return $user;
    }
}
