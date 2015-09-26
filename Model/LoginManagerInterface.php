<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;

interface LoginManagerInterface
{
    /**
     * @param $username
     *
     * @return UserInterface
     */
    public function findUserByUserName($username);

    /**
     * @param string        $firewallName
     * @param UserInterface $user
     */
    public function loginUser($firewallName, UserInterface $user);
}
