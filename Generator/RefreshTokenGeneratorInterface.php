<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Generator;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Generates a new RefreshTokenInterface instance while validating the underlying token value is unique.
 */
interface RefreshTokenGeneratorInterface
{
    public function createForUserWithTtl(UserInterface $user, int $ttl): RefreshTokenInterface;
}
