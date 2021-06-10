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

use Symfony\Component\Security\Core\User\InMemoryUser;
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

    /**
     * @deprecated use loadUserByIdentifier() instead
     */
    public function loadUserByUsername($username)
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (null !== $this->customUserProvider) {
            if (method_exists($this->customUserProvider, 'loadUserByIdentifier')) {
                return $this->customUserProvider->loadUserByIdentifier($identifier);
            }

            return $this->customUserProvider->loadUserByUsername($identifier);
        }

        if (class_exists(InMemoryUser::class)) {
            return new InMemoryUser(
                $identifier,
                null,
                ['ROLE_USER']
            );
        }

        return new User(
            $identifier,
            null,
            ['ROLE_USER']
        );
    }

    public function refreshUser(UserInterface $user)
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->refreshUser($user);
        }

        throw new UnsupportedUserException();
    }

    public function supportsClass($class)
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->supportsClass($class);
        }

        if (class_exists(InMemoryUser::class) && InMemoryUser::class === $class) {
            return true;
        }

        return User::class === $class;
    }
}
