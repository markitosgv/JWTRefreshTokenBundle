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

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RefreshTokenListener implements EventSubscriberInterface
{
    protected $refreshTokenManager;
    protected $ttl;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager, $ttl)
    {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            SecurityEvents::INTERACTIVE_LOGIN => 'refreshToken',
        );
    }

    public function refreshToken(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof UserInterface) {
            $datetime = new \DateTime();
            $datetime->modify('+'.$this->ttl.' seconds');

            $refreshToken = $this->refreshTokenManager->create();
            $refreshToken->setUsername($user->getUsername());
            $refreshToken->setValid($datetime);

            $this->refreshTokenManager->save($refreshToken);
        }
    }
}
