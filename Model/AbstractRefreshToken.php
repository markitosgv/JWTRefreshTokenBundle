<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use DateTimeInterface;
use DateTime;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractRefreshToken implements RefreshTokenInterface
{
    /**
     * @var int|string|null
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $refreshToken;

    /**
     * @var string|null
     */
    protected $username;

    /**
     * @var DateTimeInterface|null
     */
    protected $valid;

    /**
     * Creates a new model instance based on the provided details.
     */
    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): RefreshTokenInterface
    {
        $valid = new DateTime();

        // Explicitly check for a negative number based on a behavior change in PHP 8.2, see https://github.com/php/php-src/issues/9950
        if ($ttl > 0) {
            $valid->modify('+'.$ttl.' seconds');
        } elseif ($ttl < 0) {
            $valid->modify($ttl.' seconds');
        }

        $model = new static();
        $model->setRefreshToken($refreshToken);
        $model->setUsername(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername());
        $model->setValid($valid);

        return $model;
    }

    /**
     * @return string Refresh Token
     */
    public function __toString()
    {
        return $this->getRefreshToken() ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * {@inheritdoc}
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * {@inheritdoc}
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return null !== $this->valid && $this->valid >= new DateTime();
    }
}
