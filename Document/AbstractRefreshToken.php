<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Document;

use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken as BaseAbstractRefreshToken;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Abstract Refresh Token.
 *
 * @Unique("refreshToken")
 *
 * @deprecated Extend from `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken` instead
 */
abstract class AbstractRefreshToken extends BaseAbstractRefreshToken
{
    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    protected $refreshToken;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    protected $username;

    /**
     * @var \DateTimeInterface
     *
     * @Assert\NotBlank()
     */
    protected $valid;
}
