<?php

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

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
            FOSUserEvents::SECURITY_IMPLICIT_LOGIN => 'refreshToken',
            SecurityEvents::INTERACTIVE_LOGIN => 'refreshTokenInteractive',
        );
    }

    public function refreshToken(UserEvent $event)
    {
        $user = $event->getUser();

        $user->setRefreshToken(bin2hex(openssl_random_pseudo_bytes(64)));
        $this->userManager->updateUser($user);
    }
    public function refreshTokenInteractive(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof UserInterface) {
            $user->setRefreshToken(bin2hex(openssl_random_pseudo_bytes(64)));
            $this->userManager->updateUser($user);
        }
    }
}