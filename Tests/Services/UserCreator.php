<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Services;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCreator
{
    public static function create(string $identifier = 'username'): UserInterface
    {
        return new InMemoryUser($identifier, 'password');
    }
}
