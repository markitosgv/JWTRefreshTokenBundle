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
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class RefreshTokenGenerator implements RefreshTokenGeneratorInterface
{
    private RefreshTokenManagerInterface $manager;

    public function __construct(RefreshTokenManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function createForUserWithTtl(UserInterface $user, int $ttl): RefreshTokenInterface
    {
        $exists = true;

        while ($exists) {
            $token = bin2hex(random_bytes(64));

            $existingModel = $this->manager->get($token);

            $exists = null !== $existingModel;
        }

        $modelClass = $this->manager->getClass();

        return $modelClass::createForUserWithTtl($token, $user, $ttl);
    }
}
