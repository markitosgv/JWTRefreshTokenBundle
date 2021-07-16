<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Security\Provider;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class RefreshTokenProviderSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager): void
    {
        $this->beConstructedWith($refreshTokenManager);
    }

    public function it_is_a_user_provider(): void
    {
        $this->shouldImplement(UserProviderInterface::class);
    }

    public function it_gets_the_username_from_a_token_when_the_token_exists_in_storage(RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken): void
    {
        $token = 'my-refresh-token';
        $username = 'username';

        $refreshTokenManager->get($token)->willReturn($refreshToken);

        $refreshToken->getUsername()->willReturn($username);

        $this->getUsernameForRefreshToken($token)->shouldReturn($username);
    }

    public function it_returns_null_when_the_token_does_not_exist_in_storage(RefreshTokenManagerInterface $refreshTokenManager): void
    {
        $token = 'my-refresh-token';

        $refreshTokenManager->get($token)->willReturn(null);

        $this->getUsernameForRefreshToken($token)->shouldReturn(null);
    }

    public function it_loads_a_user_by_username(): void
    {
        $this->loadUserByUsername('testname')->shouldImplement(UserInterface::class);
    }

    public function it_loads_a_user_by_username_from_a_custom_user_provider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);

        $this->setCustomUserProvider($userProvider);
        $this->loadUserByUsername('testname')->shouldImplement(UserInterface::class);
    }

    public function it_loads_a_user_by_identifier(): void
    {
        $this->loadUserByIdentifier('testname')->shouldImplement(UserInterface::class);
    }

    public function it_loads_a_user_by_identifier_from_a_custom_user_provider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);

        $this->setCustomUserProvider($userProvider);
        $this->loadUserByIdentifier('testname')->shouldImplement(UserInterface::class);
    }

    public function it_does_not_support_refreshing_a_user_by_default(UserInterface $user): void
    {
        $this->shouldThrow(new UnsupportedUserException())->duringRefreshUser($user);
    }

    public function it_refreshes_a_user_when_using_a_custom_user_provider(UserInterface $user): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);

        if (method_exists($userProvider, 'loadUserByIdentifier')) {
            $user = $userProvider->loadUserByIdentifier('testname');
        } else {
            $user = $userProvider->loadUserByUsername('testname');
        }

        $this->setCustomUserProvider($userProvider);
        $this->refreshUser($user)->shouldImplement(UserInterface::class);
    }

    public function it_supports_a_user_class(): void
    {
        if (class_exists(InMemoryUser::class)) {
            $this->supportsClass(InMemoryUser::class)->shouldBe(true);
        }

        $this->supportsClass(User::class)->shouldBe(true);
    }

    public function it_supports_a_user_class_when_using_a_custom_provider(): void
    {
        $userProvider = new InMemoryUserProvider(['testname' => ['password' => 'secure-password']]);

        $this->setCustomUserProvider($userProvider);

        if (class_exists(InMemoryUser::class)) {
            $this->supportsClass(InMemoryUser::class)->shouldBe(true);
        }

        $this->supportsClass(User::class)->shouldBe(true);
    }
}
