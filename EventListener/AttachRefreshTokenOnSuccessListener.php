<?php

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class AttachRefreshTokenOnSuccessListener
{
    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data['refresh_token'] = $user->getRefreshToken();

        $event->setData($data);
    }
}