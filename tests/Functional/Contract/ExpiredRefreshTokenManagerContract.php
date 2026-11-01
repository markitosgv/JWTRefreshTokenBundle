<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Model\ExpiredRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * What every manager that clears expired tokens directly has to do, whatever it stores them in.
 *
 * Kept apart from RefreshTokenManagerContract because ExpiredRefreshTokenManagerInterface is a
 * separate interface: a manager may implement only the first, and one written outside the bundle
 * still has to pass that one on its own.
 */
trait ExpiredRefreshTokenManagerContract
{
    abstract protected function expiringManager(): ExpiredRefreshTokenManagerInterface&RefreshTokenManagerInterface;

    public function test_clears_the_expired_tokens_and_leaves_the_usable_ones(): void
    {
        $manager = $this->expiringManager();

        $this->storeLastingFor($manager, 'expired', -600);
        $this->storeLastingFor($manager, 'still-valid', 600);

        $this->assertSame(1, $manager->revokeAllExpired());

        $this->assertNull($manager->get('expired'));
        $this->assertNotNull($manager->get('still-valid'));
    }

    public function test_counts_every_token_it_cleared(): void
    {
        $manager = $this->expiringManager();

        foreach (['first', 'second', 'third'] as $token) {
            $this->storeLastingFor($manager, $token, -600);
        }

        $this->assertSame(3, $manager->revokeAllExpired());
    }

    public function test_reports_none_when_nothing_has_expired(): void
    {
        $manager = $this->expiringManager();

        $this->storeLastingFor($manager, 'still-valid', 600);

        $this->assertSame(0, $manager->revokeAllExpired());
        $this->assertNotNull($manager->get('still-valid'));
    }

    /**
     * The moment given is what counts as expired, which is what makes the command's date argument
     * mean anything: a token still usable now is cleared when asked for a date it has expired by.
     */
    public function test_takes_the_given_moment_as_the_one_to_have_expired_by(): void
    {
        $manager = $this->expiringManager();

        $this->storeLastingFor($manager, 'lasts-ten-minutes', 600);
        $this->storeLastingFor($manager, 'lasts-a-day', 86400);

        $this->assertSame(1, $manager->revokeAllExpired(new DateTime('+1 hour')));

        $this->assertNull($manager->get('lasts-ten-minutes'));
        $this->assertNotNull($manager->get('lasts-a-day'));
    }

    /**
     * Housekeeping over the whole table rather than over one account, unlike everything on
     * RevokeRefreshTokenManagerInterface.
     */
    public function test_clears_the_expired_tokens_of_every_user(): void
    {
        $manager = $this->expiringManager();

        $this->storeLastingFor($manager, 'theirs', -600, 'someone');
        $this->storeLastingFor($manager, 'not-theirs', -600, 'somebody-else');

        $this->assertSame(2, $manager->revokeAllExpired());

        $this->assertNull($manager->get('theirs'));
        $this->assertNull($manager->get('not-theirs'));
    }

    private function storeLastingFor(RefreshTokenManagerInterface $manager, string $token, int $ttl, string $username = 'someone'): void
    {
        $class = $manager->getClass();

        $manager->save($class::createForUserWithTtl($token, UserCreator::create($username), $ttl));
    }
}
