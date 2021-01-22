<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Provider;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * Class RefreshTokenProvider.
 */
class RefreshTokenProvider implements UserProviderInterface
{
    /**
     * @var RefreshTokenManagerInterface
     */
    protected $refreshTokenManager;

    /**
     * @var UserProviderInterface
     */
    protected $customUserProvider;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->refreshTokenManager = $refreshTokenManager;
    }

    public function setCustomUserProvider(UserProviderInterface $customUserProvider)
    {
        $this->customUserProvider = $customUserProvider;
    }

    public function getUsernameForRefreshToken($token)
    {
        $refreshToken = $this->refreshTokenManager->get($token);

        if ($refreshToken instanceof RefreshTokenInterface) {
            return $refreshToken->getUsername();
        }

        return null;
    }

    public function loadUserByUsername($username)
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->loadUserByUsername($username);
        } else {
            return new User(
                $username,
                null,
                ['ROLE_USER']
            );
        }
    }

    public function refreshUser(UserInterface $user)
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->refreshUser($user);
        } else {
            throw new UnsupportedUserException();
        }
    }

    public function supportsClass($class)
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->supportsClass($class);
        } else {
            return 'Symfony\Component\Security\Core\User\User' === $class;
        }
    }
}
