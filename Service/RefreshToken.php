<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;

/**
 * Class RefreshToken.
 */
class RefreshToken
{
    private $authenticator;
    private $provider;
    private $successHandler;
    private $failureHandler;
    private $refreshTokenManager;
    private $ttl;
    private $ttlUpdate;

    public function __construct(RefreshTokenAuthenticator $authenticator, RefreshTokenProvider $provider, AuthenticationSuccessHandler $successHandler, AuthenticationFailureHandler $failureHandler, RefreshTokenManagerInterface $refreshTokenManager, $ttl, $providerKey, $ttlUpdate)
    {
        $this->authenticator = $authenticator;
        $this->provider = $provider;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->providerKey = $providerKey;
        $this->ttlUpdate = $ttlUpdate;
    }

    /**
     * Refresh token.
     *
     * @param Request $request
     *
     * @return mixed
     *
     * @throws AuthenticationException
     */
    public function refresh(Request $request)
    {
        try {
            $preAuthenticatedToken = $this->authenticator->authenticateToken(
                    $this->authenticator->createToken($request, $this->providerKey), $this->provider, $this->providerKey
            );
        } catch (AuthenticationException $e) {
            return $this->failureHandler->onAuthenticationFailure($request, $e);
        }

        $refreshToken = $this->refreshTokenManager->get($preAuthenticatedToken->getCredentials());

        if (null === $refreshToken || !$refreshToken->isValid()) {
            return $this->failureHandler->onAuthenticationFailure($request, new AuthenticationException(
                            sprintf('Refresh token "%s" is invalid.', $refreshToken)
                            )
            );
        }

        if ($this->ttlUpdate) {
            $expirationDate = new \DateTime();
            $expirationDate->modify(sprintf('+%d seconds', $this->ttl));
            $refreshToken->setValid($expirationDate);

            $this->refreshTokenManager->save($refreshToken);
        }

        return $this->successHandler->onAuthenticationSuccess($request, $preAuthenticatedToken);
    }
}
