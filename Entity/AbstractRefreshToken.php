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

use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken as BaseAbstractRefreshToken;

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, use "%s" instead.', AbstractRefreshToken::class, BaseAbstractRefreshToken::class);

/**
 * @deprecated Extend from `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken` instead
 */
abstract class AbstractRefreshToken extends BaseAbstractRefreshToken
{
}
