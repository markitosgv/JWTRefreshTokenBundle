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

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Class RefreshTokenProvider.
 */
class RefreshTokenProvider implements UserProviderInterface
{
    protected $refreshTokenManager;
    protected $customUserProvider;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->refreshTokenManager = $refreshTokenManager;
    }

    public function setCustomUserProvider(UserProviderInterface $customUserProvider): void
    {
        $this->customUserProvider = $customUserProvider;
    }

    public function getUsernameForRefreshToken($token): ? string
    {
        $refreshToken = $this->refreshTokenManager->get($token);

        if ($refreshToken instanceof RefreshTokenInterface) {
            return $refreshToken->getUsername();
        }

        return null;
    }

    public function loadUserByUsername($username): UserInterface
    {
        if ($this->customUserProvider instanceof UserProviderInterface) {
            return $this->customUserProvider->loadUserByUsername($username);
        }

        return new User(
            $username,
            null,
            ['ROLE_USER']
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($this->customUserProvider instanceof UserProviderInterface) {
            return $this->customUserProvider->refreshUser($user);
        }

        throw new UnsupportedUserException();
    }

    public function supportsClass($class): bool
    {
        if ($this->customUserProvider instanceof UserProviderInterface) {
            return $this->customUserProvider->supportsClass($class);
        }

        return 'Symfony\Component\Security\Core\User\User' === $class;
    }
}
