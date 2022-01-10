<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Entity;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Unit\AbstractRefreshTokenTest;
use Symfony\Component\Security\Core\User\UserInterface;

class RefreshTokenTest extends AbstractRefreshTokenTest
{
    protected function createRefreshToken(string $refreshToken, UserInterface $user, int $ttl): RefreshTokenInterface
    {
        return RefreshToken::createForUserWithTtl('token', $user, 600);
    }
}
