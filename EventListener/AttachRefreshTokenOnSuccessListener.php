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
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;

class AttachRefreshTokenOnSuccessListener
{
    /**
     * @var RefreshTokenManagerInterface
     */
    protected $userRefreshTokenManager;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var RequestStack
     */
    protected $request;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    /**
     * @var string
     */
    protected $identityField;

    /**
     * AttachRefreshTokenOnSuccessListener constructor.
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param $ttl
     * @param ValidatorInterface $validator
     * @param Request $request
     * @param $identityField
     */
    public function __construct(RefreshTokenManagerInterface $refreshTokenManager, $ttl, ValidatorInterface $validator, Request $request, PropertyAccessorInterface $propertyAccessor, $identityField)
    {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->validator = $validator;
        $this->request = $request;
        $this->propertyAccessor = $propertyAccessor;
        $this->identityField = $identityField;
    }

    /**
     * @param AuthenticationSuccessEvent $event
     */
    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        if ($refreshTokenString = RequestRefreshToken::getRefreshToken($this->request)) {
            $data['refresh_token'] = $refreshTokenString;
            $event->setData($data);

            return;
        }

        $datetime = new \DateTime();
        $datetime->modify('+'.$this->ttl.' seconds');

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($this->propertyAccessor->getValue($user, $this->identityField));
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

        $event->setData($data);
    }
}
