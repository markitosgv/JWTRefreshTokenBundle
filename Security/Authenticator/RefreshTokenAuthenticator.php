<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\HttpFoundation\Response;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;

/**
 * Class RefreshTokenAuthenticator.
 */
class RefreshTokenAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var UserCheckerInterface
     */
    private $userChecker;

    /**
     * @var string
     */
    protected $tokenParameterName;

    /**
     * Constructor.
     *
     * @param string $tokenParameterName
     */
    public function __construct(UserCheckerInterface $userChecker, $tokenParameterName)
    {
        $this->userChecker = $userChecker;
        $this->tokenParameterName = $tokenParameterName;
    }

    public function supports(Request $request)
    {
        return null !== RequestRefreshToken::getRefreshToken($request, $this->tokenParameterName);
    }

    public function getCredentials(Request $request)
    {
        return [
            'token' => RequestRefreshToken::getRefreshToken($request, $this->tokenParameterName),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if (!$userProvider instanceof RefreshTokenProvider) {
            throw new \InvalidArgumentException(sprintf('The user provider must be an instance of RefreshTokenProvider (%s was given).', get_class($userProvider)));
        }

        $refreshToken = $credentials['token'];

        $username = $userProvider->getUsernameForRefreshToken($refreshToken);

        if (null === $username) {
            throw new AuthenticationException(sprintf('Refresh token "%s" does not exist.', $refreshToken));
        }

        $user = $userProvider->loadUserByUsername($username);

        if (null === $user) {
            throw new AuthenticationException(sprintf('User with refresh token "%s" does not exist.', $refreshToken));
        }

        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // check credentials - e.g. make sure the password is valid
        // no credential check is needed in this case

        // return true to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new Response('Refresh token authentication failed.', 403);
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            // you might translate this message
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
