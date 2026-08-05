<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * What every manager that revokes by user has to do, whatever it stores the tokens in.
 *
 * Kept apart from RefreshTokenManagerContract because RevokeRefreshTokenManagerInterface is a
 * separate interface: a manager may implement only the first, and one written outside the bundle
 * still has to pass that one on its own.
 */
trait RevokeRefreshTokenManagerContract
{
    abstract protected function revokingManager(): RevokeRefreshTokenManagerInterface&RefreshTokenManagerInterface;

    public function test_revokes_every_token_of_one_user_and_leaves_the_others(): void
    {
        $manager = $this->revokingManager();

        $this->storeFor($manager, 'someone', 'first', 600);
        $this->storeFor($manager, 'someone', 'second', 600);
        $this->storeFor($manager, 'somebody-else', 'theirs', 600);

        $this->assertSame(2, $manager->revokeAllForUser(UserCreator::create('someone')));
        $this->assertNull($manager->get('first'));
        $this->assertNotNull($manager->get('theirs'), 'Another user should keep their token');
    }

    public function test_revoking_a_user_without_tokens_reports_none(): void
    {
        $this->assertSame(0, $this->revokingManager()->revokeAllForUser(UserCreator::create('nobody')));
    }

    /**
     * The one a device limit rests on: the newest are kept, so signing in on a fourth device with a
     * limit of three ends the session that has gone longest without being refreshed.
     */
    public function test_keeps_the_newest_tokens_of_a_user_and_revokes_the_rest(): void
    {
        $manager = $this->revokingManager();

        // Stored out of order, so an implementation keeping the first rows rather than the newest
        // fails
        $this->storeFor($manager, 'someone', 'expires-in-an-hour', 3600);
        $this->storeFor($manager, 'someone', 'expires-in-a-week', 604800);
        $this->storeFor($manager, 'someone', 'expires-in-a-minute', 60);
        $this->storeFor($manager, 'someone', 'expires-in-a-day', 86400);

        $this->assertSame(2, $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 2));

        $this->assertNotNull($manager->get('expires-in-a-week'));
        $this->assertNotNull($manager->get('expires-in-a-day'));
        $this->assertNull($manager->get('expires-in-an-hour'));
        $this->assertNull($manager->get('expires-in-a-minute'));
    }

    public function test_keeps_every_token_of_a_user_below_the_limit(): void
    {
        $manager = $this->revokingManager();

        $this->storeFor($manager, 'someone', 'only-one', 600);

        $this->assertSame(0, $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 5));
        $this->assertNotNull($manager->get('only-one'));
    }

    public function test_does_not_touch_the_tokens_of_other_users_when_pruning(): void
    {
        $manager = $this->revokingManager();

        $this->storeFor($manager, 'someone', 'theirs-old', 60);
        $this->storeFor($manager, 'someone', 'theirs-new', 600);
        $this->storeFor($manager, 'somebody-else', 'not-theirs-old', 60);
        $this->storeFor($manager, 'somebody-else', 'not-theirs-new', 600);

        $this->assertSame(1, $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 1));

        $this->assertNotNull($manager->get('not-theirs-old'), 'Another user keeps every one of theirs');
        $this->assertNotNull($manager->get('not-theirs-new'));
    }

    /**
     * An expired token sorts to the end, so it goes before any that could still be used. A user at
     * the limit is not made to give up a live session while a dead one is kept.
     */
    public function test_revokes_the_expired_tokens_of_a_user_before_the_usable_ones(): void
    {
        $manager = $this->revokingManager();

        $this->storeFor($manager, 'someone', 'expired', -600);
        $this->storeFor($manager, 'someone', 'still-valid', 600);

        $this->assertSame(1, $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 1));

        $this->assertNotNull($manager->get('still-valid'));
        $this->assertNull($manager->get('expired'));
    }

    private function storeFor(RefreshTokenManagerInterface $manager, string $username, string $token, int $ttl): RefreshTokenInterface
    {
        $class = $manager->getClass();
        $refreshToken = $class::createForUserWithTtl($token, UserCreator::create($username), $ttl);

        $manager->save($refreshToken);

        return $refreshToken;
    }
}
