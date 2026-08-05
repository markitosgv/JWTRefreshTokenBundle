<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * What every manager that lists a user's tokens has to do, whatever it stores them in.
 *
 * The order is part of the contract rather than an accident of the query: a screen showing somebody
 * the sessions they have open wants the one that lasts longest at the top, and it is the same order
 * revokeAllButNewestForUser() keeps from.
 */
trait ListRefreshTokenManagerContract
{
    abstract protected function listingManager(): ListRefreshTokenManagerInterface&RefreshTokenManagerInterface;

    public function test_returns_the_tokens_of_a_user_expiring_last_first(): void
    {
        $manager = $this->listingManager();

        // Stored out of order, so an implementation returning them as they were written fails
        $this->storeOne($manager, 'someone', 'expires-in-a-day', 86400);
        $this->storeOne($manager, 'someone', 'expires-in-a-minute', 60);
        $this->storeOne($manager, 'someone', 'expires-in-a-week', 604800);

        $tokens = $manager->findAllForUser(UserCreator::create('someone'));

        $this->assertSame(
            ['expires-in-a-week', 'expires-in-a-day', 'expires-in-a-minute'],
            array_map(static fn (RefreshTokenInterface $token): ?string => $token->getRefreshToken(), $tokens)
        );
    }

    public function test_returns_nothing_for_a_user_without_tokens(): void
    {
        $this->assertSame([], $this->listingManager()->findAllForUser(UserCreator::create('nobody')));
    }

    public function test_returns_only_the_tokens_of_the_user_asked_about(): void
    {
        $manager = $this->listingManager();

        $this->storeOne($manager, 'someone', 'theirs', 600);
        $this->storeOne($manager, 'somebody-else', 'not-theirs', 600);

        $tokens = $manager->findAllForUser(UserCreator::create('someone'));

        $this->assertCount(1, $tokens);
        $this->assertSame('theirs', $tokens[0]->getRefreshToken());
    }

    /**
     * Expired tokens are still rows, so they are still returned. Hiding them would mean a caller
     * counting sessions and a caller clearing the table disagreeing about what is in it.
     */
    public function test_includes_the_expired_tokens_and_leaves_telling_them_apart_to_the_caller(): void
    {
        $manager = $this->listingManager();

        $this->storeOne($manager, 'someone', 'expired', -600);
        $this->storeOne($manager, 'someone', 'still-valid', 600);

        $tokens = $manager->findAllForUser(UserCreator::create('someone'));

        $this->assertCount(2, $tokens);
        $this->assertCount(1, array_filter($tokens, static fn (RefreshTokenInterface $token): bool => $token->isValid()));
    }

    private function storeOne(RefreshTokenManagerInterface $manager, string $username, string $token, int $ttl): void
    {
        $class = $manager->getClass();

        $manager->save($class::createForUserWithTtl($token, UserCreator::create($username), $ttl));
    }
}
