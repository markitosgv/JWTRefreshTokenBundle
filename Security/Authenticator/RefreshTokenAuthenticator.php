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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\HttpFoundation\Response;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;

if (interface_exists('Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface')) {
    abstract class RefreshTokenAuthenticatorBase implements \Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface
    {
    }
} else {
    abstract class RefreshTokenAuthenticatorBase implements \Symfony\Component\Security\Core\Authentication\SimplePreAuthenticatorInterface
    {
    }
}

/**
 * Class RefreshTokenAuthenticator.
 */
class RefreshTokenAuthenticator extends RefreshTokenAuthenticatorBase implements AuthenticationFailureHandlerInterface
{
    public function createToken(Request $request, $providerKey)
    {
        $refreshTokenString = RequestRefreshToken::getRefreshToken($request);

        return new PreAuthenticatedToken(
            '',
            $refreshTokenString,
            $providerKey
        );
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        if (!$userProvider instanceof RefreshTokenProvider) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The user provider must be an instance of RefreshTokenProvider (%s was given).',
                    get_class($userProvider)
                )
            );
        }

        $refreshToken = $token->getCredentials();
        $username = $userProvider->getUsernameForRefreshToken($refreshToken);

        if (!$username) {
            throw new AuthenticationException(
                sprintf('Refresh token "%s" does not exist.', $refreshToken)
            );
        }

        $user = $userProvider->loadUserByUsername($username);

        return new PreAuthenticatedToken(
            $user,
            $refreshToken,
            $providerKey,
            $user->getRoles()
        );
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof PreAuthenticatedToken && $token->getProviderKey() === $providerKey;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new Response('Refresh token authentication failed.', 403);
    }
}
