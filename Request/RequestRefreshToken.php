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

use Gesdinet\JWTRefreshTokenBundle\NameGenerator\NameGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service to extract refresh token from a request object.
 */
class RequestRefreshToken
{
    /**
     * @var NameGeneratorInterface
     */
    private $nameGenerator;

    /**
     * Injects dependencies.
     *
     * @param NameGeneratorInterface $nameGenerator
     */
    public function __construct(NameGeneratorInterface $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    public function getRefreshToken(Request $request)
    {
        $refreshTokenString = null;
        $refreshTokenName = $this->getRefreshTokenName();

        if (false !== strpos($request->getContentType(), 'json')) {
            $content = $request->getContent();
            $params = !empty($content) ? json_decode($content, true) : array();
            $refreshTokenString = isset($params[$refreshTokenName]) ? trim($params[$refreshTokenName]) : null;
        } elseif (null !== $request->get($refreshTokenName)) {
            $refreshTokenString = $request->get($refreshTokenName);
        } elseif (null !== $request->request->get($refreshTokenName)) {
            $refreshTokenString = $request->request->get($refreshTokenName);
        }

        return $refreshTokenString;
    }

    /**
     * Returns the name of the access token based on the current naming convention.
     *
     * @return string
     */
    private function getRefreshTokenName()
    {
        return $this->nameGenerator->generateName('refresh_token');
    }
}
