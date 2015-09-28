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
 * Class RefreshTokenProvider
 *
 * @package Gesdinet\JWTRefreshTokenBundle\Security\Provider
 */
class RefreshTokenProvider implements UserProviderInterface
{
    protected $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->refreshTokenManager = $refreshTokenManager;
    }

    public function getUsernameForRefreshToken($token)
    {
        $refreshToken = $this->refreshTokenManager->get($token);

        if ($refreshToken instanceof RefreshTokenInterface) {
            return $refreshToken->getUsername();
        }

        return;
    }

    public function loadUserByUsername($username)
    {
        return new User(
            $username,
            null,
            array('ROLE_USER')
        );
    }

    public function refreshUser(UserInterface $user)
    {
        throw new UnsupportedUserException();
    }

    public function supportsClass($class)
    {
        return 'Symfony\Component\Security\Core\User\User' === $class;
    }
}
