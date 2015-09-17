<?php

namespace Gesdinet\JWTRefreshTokenBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RefreshTokenController extends Controller
{

    /**
     * @Route("/token/refresh", name="api_refresh_token")
     */
    public function refreshTokenAction(Request $request)
    {
        $refreshToken = $request->request->get('refresh_token');

        $user = $this->getDoctrine()->getManager($this->getParameter('fos_user.model_manager_name'))->getRepository('CustomerBundle:Customer')->findOneBy(array('refreshToken' => $refreshToken));

        $exception = new AuthenticationException($refreshToken);
        if (null === $user) {
            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        $this->get('fos_user.security.login_manager')->logInUser('api', $user);
        $token = $this->get('security.token_storage')->getToken();

        $user->setRefreshToken(bin2hex(openssl_random_pseudo_bytes(64)));
        $this->get('fos_user.user_manager')->updateUser($user);

        return $this->get('lexik_jwt_authentication.handler.authentication_success')->onAuthenticationSuccess($request, $token);
    }
}
