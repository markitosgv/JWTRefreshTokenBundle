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
    public static function getRefreshToken(Request $request, $property)
    {
        $refreshTokenString = null;
        if ($request->headers->get('content_type') == 'application/json') {
            $content = $request->getContent();
            $params = !empty($content) ? json_decode($content, true) : array();
            $refreshTokenString = isset($params[$property]) ? trim($params[$property]) : null;
        } elseif (null !== $request->get($property)) {
            $refreshTokenString = $request->get($property);
        } elseif (null !== $request->request->get($property)) {
            $refreshTokenString = $request->request->get($property);
        }

        return $refreshTokenString;
    }
}
