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

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, implement "%s" directly.', RefreshTokenManager::class, RefreshTokenManagerInterface::class);

/**
 * @deprecated to be removed in 2.0, implement `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface` directly.
 */
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
