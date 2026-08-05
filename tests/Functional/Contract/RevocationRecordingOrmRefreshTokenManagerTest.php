<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevocationRecordingRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\CacheJWTRevocationRegistry;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The recording decorator wraps whichever backend is in use, so everything the manager underneath
 * does has to still arrive. The shared contracts are run against it for exactly that reason.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class RevocationRecordingOrmRefreshTokenManagerTest extends ORMTestCase
{
    use FamilyAwareRefreshTokenManagerContract;
    use ListRefreshTokenManagerContract;
    use RefreshTokenManagerContract;
    use RevokeRefreshTokenManagerContract;

    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = new ArrayAdapter();

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    /**
     * The reason the decorator exists. Without the mark, a password reset takes the refresh tokens
     * away and every JWT already issued keeps working until it expires.
     */
    public function test_revoking_a_users_sessions_marks_their_jwts_as_no_longer_acceptable(): void
    {
        $manager = $this->manager();
        $manager->save(RefreshToken::createForUserWithTtl('a-token', UserCreator::create('someone'), 600));

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);

        $this->assertSame(1, $manager->revokeAllForUser(UserCreator::create('someone')));

        $this->assertEqualsWithDelta(
            time(),
            new CacheJWTRevocationRegistry($this->pool, 3600)->revokedBefore('someone'),
            5
        );
    }

    /**
     * Pruning a user's older sessions leaves them using the newest, so marking here would sign them
     * out of the device in their hand.
     */
    public function test_pruning_a_users_older_sessions_marks_nothing(): void
    {
        $manager = $this->manager();
        $manager->save(RefreshToken::createForUserWithTtl('the-old-one', UserCreator::create('someone'), 600));
        $manager->save(RefreshToken::createForUserWithTtl('the-new-one', UserCreator::create('someone'), 900));

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);

        $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 1);

        $this->assertNull(new CacheJWTRevocationRegistry($this->pool, 3600)->revokedBefore('someone'));
    }

    /**
     * Ending one session marks nothing either: nothing on a JWT says which chain issued it, so there
     * is no way to refuse only that session's tokens.
     */
    public function test_ending_one_session_marks_nothing(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token', UserCreator::create('someone'), 600);
        $token->setFamily('a-chain');
        $manager->save($token);

        \assert($manager instanceof FamilyRefreshTokenManagerInterface);

        $manager->revokeFamily('a-chain');

        $this->assertNull(new CacheJWTRevocationRegistry($this->pool, 3600)->revokedBefore('someone'));
    }

    public function test_says_so_when_the_manager_underneath_cannot_revoke_by_user(): void
    {
        $bare = new RevocationRecordingRefreshTokenManager(
            $this->createStub(RefreshTokenManagerInterface::class),
            new CacheJWTRevocationRegistry($this->pool, 3600)
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement');

        $bare->revokeAllForUser(UserCreator::create('someone'));
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new RevocationRecordingRefreshTokenManager(
            new RefreshTokenManager($this->entityManager, RefreshToken::class, $batchSize),
            new CacheJWTRevocationRegistry($this->pool, 3600)
        );
    }

    protected function forgetLoadedObjects(): void
    {
        $this->entityManager->clear();
    }

    protected function revokingManager(): RevokeRefreshTokenManagerInterface&RefreshTokenManagerInterface
    {
        $manager = $this->manager();

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);

        return $manager;
    }

    protected function listingManager(): ListRefreshTokenManagerInterface&RefreshTokenManagerInterface
    {
        $manager = $this->manager();

        \assert($manager instanceof ListRefreshTokenManagerInterface);

        return $manager;
    }
}
