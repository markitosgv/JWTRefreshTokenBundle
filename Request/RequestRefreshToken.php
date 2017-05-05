<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Request;

use Symfony\Component\HttpFoundation\Request;

class RequestRefreshToken
{
    public static function getRefreshToken(Request $request)
    {
        $refreshTokenString = null;
        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        if ($request->getMethod() === 'POST') {
            $inputData = $request->request->all();
        } else {
            $inputData = $request->query->all();
        }

        if (array_key_exists('refresh_token', $inputData)) {
            $refreshTokenString = $inputData['refresh_token'];
        }

        return $refreshTokenString;
    }
}
