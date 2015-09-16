<?php

namespace Gesdinet\JWTRefreshTokenBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RefreshTokenController extends Controller
{

    /**
     * @Route("/token/refresh", name="api_refresh_token")
     */
    public function refreshTokenAction(Request $request)
    {
        $refreshToken = $request->request->get('refresh_token');
        $username = $request->request->get('username');

        $user = $this->getDoctrine()->getManager('main')->getRepository('CustomerBundle:Customer')->findOneBy(array($this->getParameter('lexik_jwt_authentication.user_identity_field') => $username, 'refreshToken' => $refreshToken));

        $exception = new AuthenticationException($refreshToken);
        if (null === $user) {
            return $this->get('lexik_jwt_authentication.handler.authentication_failure')->onAuthenticationFailure($request, $exception);
        }

        $this->get('fos_user.security.login_manager')->logInUser('api', $user);
        $token = $this->get('security.token_storage')->getToken();
        $event = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $event);

        return $this->get('lexik_jwt_authentication.handler.authentication_success')->onAuthenticationSuccess($request, $token);
    }
}
