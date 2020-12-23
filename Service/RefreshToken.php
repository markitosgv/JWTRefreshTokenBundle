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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Class RefreshToken.
 */
class RefreshToken
{
    /**
     * @var RefreshTokenAuthenticator
     */
    private $authenticator;

    /**
     * @var RefreshTokenProvider
     */
    private $provider;

    /**
     * @var AuthenticationSuccessHandlerInterface
     */
    private $successHandler;

    /**
     * @var AuthenticationFailureHandlerInterface
     */
    private $failureHandler;

    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var string
     */
    private $providerKey;

    /**
     * @var bool
     */
    private $ttlUpdate;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * RefreshToken constructor.
     *
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
     * Refresh token.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     */
    public function refresh(Request $request)
    {
        try {
            $user = $this->authenticator->getUser(
                $this->authenticator->getCredentials($request),
                $this->provider
            );

            $postAuthenticationToken = $this->authenticator->createAuthenticatedToken($user, $this->providerKey);
        } catch (AuthenticationException $e) {
            return $this->failureHandler->onAuthenticationFailure($request, $e);
        }

        $credentials = $this->authenticator->getCredentials($request);
        $refreshToken = $this->refreshTokenManager->get($credentials['token']);

        if (null === $refreshToken || !$refreshToken->isValid()) {
            return $this->failureHandler->onAuthenticationFailure(
                $request,
                new AuthenticationException(
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

        if ($this->eventDispatcher instanceof ContractsEventDispatcherInterface) {
            $this->eventDispatcher->dispatch(new RefreshEvent($refreshToken, $postAuthenticationToken), 'gesdinet.refresh_token');
        } else {
            $this->eventDispatcher->dispatch('gesdinet.refresh_token', new RefreshEvent($refreshToken, $postAuthenticationToken));
        }

        return $this->successHandler->onAuthenticationSuccess($request, $postAuthenticationToken);
    }
}
