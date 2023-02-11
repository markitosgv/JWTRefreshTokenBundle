<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Request\Extractor;

use Symfony\Component\HttpFoundation\Request;

final class RequestBodyExtractor implements ExtractorInterface
{
    public function getRefreshToken(Request $request, string $parameter): ?string
    {
        $contentType = method_exists(Request::class, 'getContentTypeFormat') ? $request->getContentTypeFormat() : $request->getContentType();

        if (null === $contentType || false === strpos($contentType, 'json')) {
            return null;
        }

        $content = $request->getContent();
        $params = !empty($content) ? json_decode($content, true) : [];

        return isset($params[$parameter]) ? trim($params[$parameter]) : null;
    }
}
