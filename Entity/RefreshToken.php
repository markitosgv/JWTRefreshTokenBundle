<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Refresh Token.
 *
 * @ORM\Table("refresh_tokens")
 * @ORM\Entity(repositoryClass="Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository")
 * @UniqueEntity("refreshToken")
 */
class RefreshToken implements RefreshTokenInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="refresh_token", type="string", length=128, unique=true)
     * @Assert\NotBlank()
     */
    protected $refreshToken;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255)
     * @Assert\NotBlank()
     */
    protected $username;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="valid", type="datetime")
     * @Assert\NotBlank()
     */
    protected $valid;

    public function __toString()
    {
        return (string) $this->getRefreshToken();
    }

    public function getId(): ? int
    {
        return $this->id;
    }

    public function setRefreshToken(string $refreshToken = null): RefreshTokenInterface
    {
        if (null === $refreshToken) {
            $this->refreshToken = bin2hex(openssl_random_pseudo_bytes(64));
        } else {
            $this->refreshToken = $refreshToken;
        }

        return $this;
    }

    public function getRefreshToken(): ? string
    {
        return $this->refreshToken;
    }

    public function setValid(\DateTime $valid): RefreshTokenInterface
    {
        $this->valid = $valid;

        return $this;
    }

    public function getValid(): ? \DateTime
    {
        return $this->valid;
    }

    public function setUsername(string $username): RefreshTokenInterface
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ? string
    {
        return $this->username;
    }

    public function isValid(): bool
    {
        $datetime = new \DateTime();

        return $this->valid >= $datetime;
    }
}
