<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Services;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCreator
{
    public static function create(): UserInterface
    {
        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        return $user;
    }
}
