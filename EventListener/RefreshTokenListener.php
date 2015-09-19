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

use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RefreshTokenListener implements EventSubscriberInterface
{
    protected $userRefreshTokenManager;
    protected $ttl;

    public function __construct(UserRefreshTokenManagerInterface $userRefreshTokenManager, $ttl)
    {
        $this->userRefreshTokenManager = $userRefreshTokenManager;
        $this->ttl = $ttl;
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

        $datetime = new \DateTime();
        $datetime->modify("+".$this->ttl." seconds");

        $userRefreshToken = $this->userRefreshTokenManager->create();
        $userRefreshToken->setUser($user);
        $userRefreshToken->setRefreshToken();
        $userRefreshToken->setValid($datetime);

        $this->userRefreshTokenManager->save($userRefreshToken);
    }

    public function refreshTokenInteractive(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof UserInterface) {
            $datetime = new \DateTime();
            $datetime->modify("+".$this->ttl." seconds");

            $userRefreshToken = $this->userRefreshTokenManager->create();
            $userRefreshToken->setUser($user);
            $userRefreshToken->setRefreshToken();
            $userRefreshToken->setValid($datetime);

            $this->userRefreshTokenManager->save($userRefreshToken);
        }
    }
}