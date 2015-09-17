<?php

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefreshTokenListener implements EventSubscriberInterface
{
    protected $userManager;

    public function __construct(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::AUTHENTICATION_SUCCESS => 'refreshToken',
        );
    }

    public function refreshToken(AuthenticationSuccessEvent $event)
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $user->setRefreshToken(bin2hex(openssl_random_pseudo_bytes(64)));
        $this->userManager->updateUser($user);
    }

}