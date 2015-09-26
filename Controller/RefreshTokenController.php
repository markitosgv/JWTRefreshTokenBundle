<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RefreshTokenController extends Controller
{
    /**
     * @Route("/api/token/refresh", name="api_refresh_token")
     */
    public function refreshTokenAction(Request $request)
    {
        $refreshTokenParam = $request->request->get('refresh_token');
        $refreshTokenManager = $this->get('gesdinet.jwtrefreshtoken.refresh_token_manager');

        $refreshToken = $refreshTokenManager->get($refreshTokenParam);

        if (null === $refreshToken || !$refreshToken->isValid()) {
            $exception = new AuthenticationException($refreshTokenParam);

            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        try {
            $user = $this->get('gesdinet.jwtrefreshtoken.login_manager')->findUserByUserName($refreshToken->getUsername());
        } catch (\Exception $e) {
            $exception = new AuthenticationException($refreshTokenParam);

            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        $this->get('gesdinet.jwtrefreshtoken.login_manager')->loginUser('api', $user);
        $token = $this->get('security.token_storage')->getToken();

        $ttl = $this->getParameter('gesdinet_jwt_refresh_token.ttl');
        $datetime = new \DateTime();
        $datetime->modify('+'.$ttl.' seconds');

        $refreshToken->renewRefreshToken();
        $refreshToken->setValid($datetime);
        $refreshTokenManager->save($refreshToken);

        return $this->get('lexik_jwt_authentication.handler.authentication_success')->onAuthenticationSuccess($request, $token);
    }
}