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
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface as ModelRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

/**
 * Class RefreshToken.
 */
class RefreshToken implements RefreshTokenInterface
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
    private $userIdentityField;

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
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * RefreshToken constructor.
     *
     * @param int    $ttl
     * @param string $providerKey
     * @param bool   $ttlUpdate
     * @param string $userIdentityField
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
        $userIdentityField,
        EventDispatcherInterface $eventDispatcher,
        ValidatorInterface $validator
    ) {
        $this->authenticator = $authenticator;
        $this->provider = $provider;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->userIdentityField = $userIdentityField;
        $this->providerKey = $providerKey;
        $this->ttlUpdate = $ttlUpdate;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
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

    /**
     * Creates a token.
     *
     * @return ModelRefreshTokenInterface The refresh token
     */
    public function create(UserInterface $user)
    {
        $datetime = new \DateTime();
        $datetime->modify('+' . $this->ttl . ' seconds');

        $refreshToken = $this->refreshTokenManager->create();

        $accessor = new PropertyAccessor();
        $userIdentityFieldValue = $accessor->getValue($user, $this->userIdentityField);

        $refreshToken->setUsername($userIdentityFieldValue);
        $refreshToken->setRefreshToken();
        $refreshToken->setValid($datetime);

        $valid = false;
        while (false === $valid) {
            $valid = true;
            $errors = $this->validator->validate($refreshToken);
            if ($errors->count() > 0) {
                foreach ($errors as $error) {
                    if ('refreshToken' === $error->getPropertyPath()) {
                        $valid = false;
                        $refreshToken->setRefreshToken();
                    }
                }
            }
        }

        $this->refreshTokenManager->save($refreshToken);

        return $refreshToken;
    }
}
