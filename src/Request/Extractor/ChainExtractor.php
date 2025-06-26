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

final class ChainExtractor implements ExtractorInterface
{
    /**
     * @var ExtractorInterface[]
     */
    private array $extractors = [];

    public function addExtractor(ExtractorInterface $extractor): void
    {
        $this->extractors[] = $extractor;
    }

    public function getRefreshToken(Request $request, string $parameter): ?string
    {
        foreach ($this->extractors as $extractor) {
            if (null !== $token = $extractor->getRefreshToken($request, $parameter)) {
                return $token;
            }
        }

        return null;
    }
}
