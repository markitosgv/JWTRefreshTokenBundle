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

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Exception\InvalidRefreshTokenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, use the `refresh_jwt` authenticator instead.', RefreshToken::class);

/**
 * @deprecated use the `refresh_jwt` authenticator instead
 */
class RefreshToken
{
    private RefreshTokenAuthenticator $authenticator;

    private RefreshTokenProvider $provider;

    private AuthenticationSuccessHandlerInterface $successHandler;

    private AuthenticationFailureHandlerInterface $failureHandler;

    private RefreshTokenManagerInterface $refreshTokenManager;

    private int $ttl;

    private string $providerKey;

    private bool $ttlUpdate;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @param int    $ttl
     * @param string $providerKey
     * @param bool   $ttlUpdate
     */
    public function __construct(
        RefreshTokenAuthenticator $authenticator,
        RefreshTokenProvider $provider,
        AuthenticationSuccessHandlerInterface $successHandler,
        AuthenticationFailureHandlerInterface $failureHandler,
        RefreshTokenManagerInterface $refreshTokenManager,
        $ttl,
        $providerKey,
        $ttlUpdate,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->authenticator = $authenticator;
        $this->provider = $provider;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->providerKey = $providerKey;
        $this->ttlUpdate = $ttlUpdate;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return Response
     *
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     */
    public function refresh(Request $request)
    {
        $credentials = $this->authenticator->getCredentials($request);

        try {
            $user = $this->authenticator->getUser($credentials, $this->provider);

            $postAuthenticationToken = $this->authenticator->createAuthenticatedToken($user, $this->providerKey);
        } catch (AuthenticationException $e) {
            return $this->failureHandler->onAuthenticationFailure($request, $e);
        }

        $refreshToken = $this->refreshTokenManager->get($credentials['token']);

        if (null === $refreshToken || !$refreshToken->isValid()) {
            return $this->failureHandler->onAuthenticationFailure(
                $request,
                new InvalidRefreshTokenException(
                    sprintf('Refresh token "%s" is invalid.', (string) $refreshToken)
                )
            );
        }

        if ($this->ttlUpdate) {
            $expirationDate = new \DateTime();
            $expirationDate->modify(sprintf('+%d seconds', $this->ttl));
            $refreshToken->setValid($expirationDate);

            $this->refreshTokenManager->save($refreshToken);
        }

        $event = new RefreshEvent($refreshToken, $postAuthenticationToken);

        $this->eventDispatcher->dispatch($event, 'gesdinet.refresh_token');

        return $this->successHandler->onAuthenticationSuccess($request, $postAuthenticationToken);
    }
}
