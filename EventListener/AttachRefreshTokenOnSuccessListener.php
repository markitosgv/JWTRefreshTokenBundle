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
use FOS\UserBundle\Doctrine\UserManager;
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AttachRefreshTokenOnSuccessListener
{
    protected $refreshTokenManager;
    protected $ttl;
    protected $validator;
    protected $requestStack;
    protected $getUsername;
    protected $userManager;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        int $ttl,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        string $getUsername,
        UserManager $userManager
    )
    {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->validator = $validator;
        $this->requestStack = $requestStack;
        $this->userManager = $userManager;
        $this->getUsername = 'getUsername';
        if ($getUsername)
            $this->getUsername = $getUsername;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof UserInterface) {
            return;
        }

        $refreshTokenString = RequestRefreshToken::getRefreshToken($request);

        $user->setLastLogin(new \DateTime());
        $this->userManager->updateUser($user);

        if ($refreshTokenString) {
            $data['refresh_token'] = $refreshTokenString;
        } else {
            $datetime = new \DateTime();
            $datetime->modify('+'.$this->ttl.' seconds');

            $getUsername = $this->getUsername;
            $refreshToken = $this->refreshTokenManager->create();
            $refreshToken->setUsername($user->$getUsername());
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
            $data['refresh_token'] = $refreshToken->getRefreshToken();
        }

        $event->setData($data);
    }
}
