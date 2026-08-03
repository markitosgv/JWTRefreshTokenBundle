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

/**
 * @psalm-mutable
 */
interface ExtractorInterface
{
    /**
     * @psalm-impure it reads the incoming request
     */
    public function getRefreshToken(Request $request, string $parameter): ?string;
}
