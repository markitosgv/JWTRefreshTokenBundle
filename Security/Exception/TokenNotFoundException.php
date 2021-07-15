<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class TokenNotFoundException extends AuthenticationException
{
    /**
     * @return string
     */
    public function getMessageKey()
    {
        return 'JWT Refresh Token Not Found';
    }
}
