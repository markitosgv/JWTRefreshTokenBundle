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

final readonly class RefreshTokenGenerator implements RefreshTokenGeneratorInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(private RefreshTokenManagerInterface $manager)
    {
    }

    #[\Override]
    public function createForUserWithTtl(UserInterface $user, int $ttl): RefreshTokenInterface
    {
        // Keep generating until the value is not already taken. The loop body always runs once,
        // which is what guarantees $token is set below.
        do {
            $token = bin2hex(random_bytes(64));
        } while (null !== $this->manager->get($token));

        $modelClass = $this->manager->getClass();

        return $modelClass::createForUserWithTtl($token, $user, $ttl);
    }
}
