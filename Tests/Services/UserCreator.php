<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Services;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCreator
{
    public static function create(string $identifier = 'username'): UserInterface
    {
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($identifier, $password);
        } else {
            $user = new User($identifier, $password);
        }

        return $user;
    }
}
