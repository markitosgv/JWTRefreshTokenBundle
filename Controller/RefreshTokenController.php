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
        $refreshToken = $request->request->get('refresh_token');
        $username = $request->request->get('username');

        $user = $this->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        $exception = new AuthenticationException($refreshToken);

        if (null === $user) {
            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        $userRefreshTokenManager = $this->get('gesdinet.jwtrefreshtoken.user_refresh_token_manager');
        $userRefreshToken = $userRefreshTokenManager->get($refreshToken, $user);

        if (null === $userRefreshToken || !$userRefreshToken->isValid()) {
            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        $this->get('fos_user.security.login_manager')->logInUser('api', $user);
        $token = $this->get('security.token_storage')->getToken();

        $ttl = $this->getParameter('gesdinet_jwt_refresh_token.ttl');
        $datetime = new \DateTime();
        $datetime->modify('+'.$ttl.' seconds');

        $userRefreshToken->setRefreshToken();
        $userRefreshToken->setValid($datetime);

        $userRefreshTokenManager->save($userRefreshToken);

        return $this->get('lexik_jwt_authentication.handler.authentication_success')->onAuthenticationSuccess($request, $token);
    }
}
