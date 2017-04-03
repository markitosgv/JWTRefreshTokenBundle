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
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Gesdinet\JWTRefreshTokenBundle\Event\GetTokenRequestEvent;
use Gesdinet\JWTRefreshTokenBundle\Event\AddTokenResponseEvent;
use Gesdinet\JWTRefreshTokenBundle\Events;

class AttachRefreshTokenOnSuccessListener
{
    protected $userRefreshTokenManager;
    protected $ttl;
    protected $validator;
    protected $requestStack;
    protected $dispatcher;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager, $ttl, ValidatorInterface $validator, RequestStack $requestStack, EventDispatcherInterface $dispatcher)
    {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->validator = $validator;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();
        $response = $event->getResponse();

        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof UserInterface) {
            return;
        }

        $getTokenEvent = new GetTokenRequestEvent($request);
        $this->dispatcher->dispatch(Events::GET_TOKEN_REQUEST, $getTokenEvent);

        $refreshTokenString = $getTokenEvent->getToken();

        if (!$refreshTokenString) {
            $datetime = new \DateTime();
            $datetime->modify('+'.$this->ttl.' seconds');

            $refreshToken = $this->refreshTokenManager->create();
            $refreshToken->setUsername($user->getUsername());
            $refreshToken->setRefreshToken();
            $refreshToken->setValid($datetime);

            $valid = false;
            while (false === $valid) {
                $valid = true;
                $errors = $this->validator->validate($refreshToken);
                if ($errors->count() > 0) {
                    foreach ($errors as $error) {
                        if ('refreshToken' === $error->getPropertyPath()) {
                            $valid = false;
                            $refreshToken->setRefreshToken();
                        }
                    }
                }
            }

            $this->refreshTokenManager->save($refreshToken);

            $refreshTokenString = $refreshToken->getRefreshToken();
        }

        $addTokenEvent = new AddTokenResponseEvent($refreshTokenString, $response);
        $addTokenEvent->setData($data);
        $this->dispatcher->dispatch(Events::ADD_TOKEN_RESPONSE, $addTokenEvent);

        $event->setData($addTokenEvent->getData());
    }
}
