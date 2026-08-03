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
    #[\Override]
    public function getRefreshToken(Request $request, string $parameter): ?string
    {
        $contentType = $request->getContentTypeFormat();

        if (null === $contentType || !str_contains($contentType, 'json')) {
            return null;
        }

        $content = $request->getContent();
        $params = '' !== $content ? json_decode($content, true) : [];

        // The body is whatever the client sent: it may decode to a scalar, or hold anything at all
        // under the parameter
        if (!is_array($params) || !isset($params[$parameter]) || !is_scalar($params[$parameter])) {
            return null;
        }

        return trim((string) $params[$parameter]);
    }
}
