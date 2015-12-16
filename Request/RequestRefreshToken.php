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
        if ($request->headers->get('content_type') == 'application/json') {
            $content = $request->getContent();
            $params = !empty($content) ? json_decode($content, true) : array();
            $refreshTokenString = trim($params['refresh_token']);
        } elseif (null !== $request->get('refresh_token')) {
            $refreshTokenString = $request->get('refresh_token');
        } else {
            $refreshTokenString = $request->request->get('refresh_token');
        }

        return $refreshTokenString;
    }
}