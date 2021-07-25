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

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, implement "%s" directly.', RefreshTokenManager::class, RefreshTokenManagerInterface::class);

/**
 * @deprecated to be removed in 2.0, implement `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface` directly.
 */
abstract class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * Creates an empty RefreshTokenInterface instance.
     *
     * @return RefreshTokenInterface
     *
     * @deprecated to be removed in 2.0, use a `Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface` instead.
     */
    public function create()
    {
        trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', '%s() is deprecated and will be removed in 2.0, use a "%s" instance to create new %s objects.', __METHOD__, RefreshTokenGeneratorInterface::class, RefreshTokenInterface::class);

        $class = $this->getClass();

        return new $class();
    }
}
