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

use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;

/**
 * Refresh Token.
 *
 * @Unique("refreshToken")
 */
class RefreshToken extends AbstractRefreshToken
{
    /**
     * @var string
     */
    protected $id;

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }
}
