<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Gesdinet\JWTRefreshTokenBundle\Model\LoginManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LoginManager implements LoginManagerInterface
{
    private $tokenStorage;
    private $userProvider;
    private $userChecker;

    public function __construct(TokenStorageInterface $tokenStorage, UserCheckerInterface $userChecker, UserProviderInterface $userProvider)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userProvider = $userProvider;
        $this->userChecker = $userChecker;
    }

    /**
     * @param $username
     *
     * @return UserInterface
     */
    public function findUserByUserName($username)
    {
        $user = $this->userProvider->loadUserByUsername($username);

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException('The user provider must return a UserInterface object.');
        }

        return $user;
    }

    /**
     * @param string $firewallName
     * @param UserInterface $user
     */
    public function loginUser($firewallName, UserInterface $user)
    {
        $this->userChecker->checkPostAuth($user);
        $token = $this->createToken($firewallName, $user);
        $this->tokenStorage->setToken($token);
    }

    /**
     * @param string        $firewall
     * @param UserInterface $user
     *
     * @return UsernamePasswordToken
     */
    protected function createToken($firewall, UserInterface $user)
    {
        return new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
    }
}