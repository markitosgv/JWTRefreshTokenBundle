<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class AttachRefreshTokenOnSuccessListener
{
    protected $userRefreshTokenManager;

    public function __construct(UserRefreshTokenManagerInterface $userRefreshTokenManager)
    {
        $this->userRefreshTokenManager = $userRefreshTokenManager;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data['refresh_token'] = $this->userRefreshTokenManager->getLastFromUser($user)->getRefreshToken();

        $event->setData($data);
    }
}
