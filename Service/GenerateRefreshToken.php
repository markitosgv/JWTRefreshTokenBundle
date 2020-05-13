<?php


namespace Gesdinet\JWTRefreshTokenBundle\Service;

use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GenerateRefreshToken
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    /**
     * @var int
     */
    private $ttl;

    /**
     * GenerateRefreshToken constructor.
     * @param ValidatorInterface $validator
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param int $ttl
     */
    public function __construct(ValidatorInterface $validator, RefreshTokenManagerInterface $refreshTokenManager, $ttl)
    {
        $this->validator = $validator;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
    }

    /**
     * @param string $username
     * @return string
     */
    public function generate($username)
    {
        $datetime = new DateTime();
        $datetime->modify('+'.$this->ttl.' seconds');

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($username);
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

        return $refreshToken->getRefreshToken();
    }

}
