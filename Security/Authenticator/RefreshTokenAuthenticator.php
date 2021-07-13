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

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Exception\UnknownRefreshTokenException;
use Gesdinet\JWTRefreshTokenBundle\Exception\UnknownUserFromRefreshTokenException;
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

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, use the `refresh_jwt` authenticator instead.', RefreshTokenAuthenticator::class);

/**
 * Class RefreshTokenAuthenticator.
 *
 * @deprecated use the `refresh_jwt` authenticator instead
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
     * @var ExtractorInterface
     */
    protected $extractor;

    /**
     * Constructor.
     *
     * @param string $tokenParameterName
     */
    public function __construct(UserCheckerInterface $userChecker, $tokenParameterName, ExtractorInterface $extractor)
    {
        $this->userChecker = $userChecker;
        $this->tokenParameterName = $tokenParameterName;
        $this->extractor = $extractor;
    }

    public function supports(Request $request)
    {
        return null !== $this->extractor->getRefreshToken($request, $this->tokenParameterName);
    }

    public function getCredentials(Request $request)
    {
        return [
            'token' => $this->extractor->getRefreshToken($request, $this->tokenParameterName),
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
            throw new UnknownRefreshTokenException(sprintf('Refresh token "%s" does not exist.', $refreshToken));
        }

        $user = $userProvider->loadUserByUsername($username);

        if (null === $user) {
            throw new UnknownUserFromRefreshTokenException(sprintf('User with refresh token "%s" does not exist.', $refreshToken));
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
