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

abstract class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * Creates an empty RefreshToken instance.
     *
     * @return RefreshTokenInterface
     */
    public function create()
    {
        $class = $this->getClass();

        return new $class();
    }
}
