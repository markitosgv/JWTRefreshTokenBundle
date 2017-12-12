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
use Gesdinet\JWTRefreshTokenBundle\NameGenerator\NameGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AttachRefreshTokenOnSuccessListener
{
    /**
     * @var NameGeneratorInterface
     */
    private $nameGenerator;

    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var RequestRefreshToken
     */
    private $requestRefreshToken;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * Injects dependencies.
     *
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param                              $ttl
     * @param ValidatorInterface           $validator
     * @param RequestStack                 $requestStack
     * @param RequestRefreshToken          $requestRefreshToken
     * @param NameGeneratorInterface       $nameGenerator
     */
    public function __construct(RefreshTokenManagerInterface $refreshTokenManager, $ttl, ValidatorInterface $validator, RequestStack $requestStack, RequestRefreshToken $requestRefreshToken, NameGeneratorInterface $nameGenerator)
    {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->validator = $validator;
        $this->requestStack = $requestStack;
        $this->requestRefreshToken = $requestRefreshToken;
        $this->nameGenerator = $nameGenerator;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof UserInterface) {
            return;
        }

        $refreshTokenString = $this->requestRefreshToken->getRefreshToken($request);

        $refreshTokenName = $this->nameGenerator->generateName('refresh_token');

        if ($refreshTokenString) {
            $data[$refreshTokenName] = $refreshTokenString;
        } else {
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
            $data[$refreshTokenName] = $refreshToken->getRefreshToken();
        }

        $event->setData($data);
    }
}
