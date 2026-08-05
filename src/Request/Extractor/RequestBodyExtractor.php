<?php

declare(strict_types=1);

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
        $content = $request->getContent();

        if ('' === $content) {
            return null;
        }

        // A JSON body is read whatever the request says it is. A client that does not set the
        // header, which is what fetch() does when none are given, still sent the token, and the
        // reply is otherwise that no token was supplied at all
        $params = json_decode($content, true);

        // The body is whatever the client sent: it may decode to a scalar, or hold anything at all
        // under the parameter
        if (!is_array($params) || !isset($params[$parameter]) || !is_scalar($params[$parameter])) {
            return null;
        }

        return trim((string) $params[$parameter]);
    }
}
