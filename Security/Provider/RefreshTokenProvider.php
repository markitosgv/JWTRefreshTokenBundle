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

trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%s" class is deprecated, configure the user provider for the `refresh_jwt` authenticator instead.', RefreshTokenProvider::class);

if ((new \ReflectionClass(UserProviderInterface::class))->getMethod('supportsClass')->hasReturnType()) {
    /**
     * Compatibility layer for Symfony 7.0 and later, where {@see UserProviderInterface::supportsClass()} has a return type.
     *
     * @internal
     */
    abstract class CompatRefreshTokenProvider implements UserProviderInterface
    {
        /**
         * @param class-string<UserInterface> $class
         */
        public function supportsClass(string $class): bool
        {
            return $this->doSupportsClass($class);
        }

        /**
         * @param class-string<UserInterface> $class
         */
        abstract protected function doSupportsClass(string $class): bool;
    }
} else {
    /**
     * Compatibility layer for Symfony 6.4 and earlier, where {@see UserProviderInterface::supportsClass()} does not have a return type.
     *
     * @internal
     */
    abstract class CompatRefreshTokenProvider implements UserProviderInterface
    {
        /**
         * @param class-string<UserInterface> $class
         *
         * @return bool
         */
        public function supportsClass($class)
        {
            return $this->doSupportsClass($class);
        }

        /**
         * @param class-string<UserInterface> $class
         */
        abstract protected function doSupportsClass(string $class): bool;
    }
}

/**
 * @deprecated configure the user provider for the `refresh_jwt` authenticator instead
 */
class RefreshTokenProvider extends CompatRefreshTokenProvider
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

    /**
     * @return void
     */
    public function setCustomUserProvider(UserProviderInterface $customUserProvider)
    {
        $this->customUserProvider = $customUserProvider;
    }

    /**
     * @param string $token
     *
     * @return string|null
     */
    public function getUsernameForRefreshToken($token)
    {
        $refreshToken = $this->refreshTokenManager->get($token);

        if ($refreshToken instanceof RefreshTokenInterface) {
            return $refreshToken->getUsername();
        }

        return null;
    }

    /**
     * @param string $username
     *
     * @return UserInterface
     *
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

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->refreshUser($user);
        }

        throw new UnsupportedUserException();
    }

    /**
     * @param class-string<UserInterface> $class
     */
    protected function doSupportsClass(string $class): bool
    {
        if (null !== $this->customUserProvider) {
            return $this->customUserProvider->supportsClass($class);
        }

        if (class_exists(InMemoryUser::class) && InMemoryUser::class === $class) {
            return true;
        }

        return class_exists(User::class) && User::class === $class;
    }
}
