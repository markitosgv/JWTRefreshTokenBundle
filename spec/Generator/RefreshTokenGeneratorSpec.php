<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Generator;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;

class RefreshTokenGeneratorSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $manager)
    {
        $this->beConstructedWith($manager);
    }

    public function it_is_a_token_generator()
    {
        $this->shouldImplement(RefreshTokenGeneratorInterface::class);
    }

    public function it_generates_a_refresh_token_when_there_are_no_existing_tokens(RefreshTokenManagerInterface $manager)
    {
        $manager->get(Argument::type('string'))->willReturn(null);
        $manager->getClass()->willReturn(RefreshToken::class);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->createForUserWithTtl($user, 600)->shouldReturnAnInstanceOf(RefreshTokenInterface::class);
    }

    public function it_generates_a_refresh_token_when_there_is_an_existing_token_matching_the_generated_token(RefreshTokenManagerInterface $manager, RefreshTokenInterface $existingRefreshToken)
    {
        $manager->get(Argument::type('string'))->willReturn($existingRefreshToken);
        $manager->get(Argument::type('string'))->willReturn(null);
        $manager->getClass()->willReturn(RefreshToken::class);

        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->createForUserWithTtl($user, 600)->shouldReturnAnInstanceOf(RefreshTokenInterface::class);
    }
}
