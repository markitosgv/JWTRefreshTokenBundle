<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Security\ReuseDetection;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenReuseDetectedEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\HashedRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\CacheSpentRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\RefreshTokenReuseDetector;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshTokenRegistryInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reuse detection over hashed storage, which is where it used to do nothing at all.
 *
 * `hash_tokens` stores a digest instead of the token, so the model read back from storage carries
 * the hash and not the value the client holds. The spend was recorded under the model's value while
 * every replay is looked up by what the client sends, so the two keys never met: replaying a spent
 * token was refused as an unknown one, the chain was left alive, and nothing was logged to say so.
 * Reported as #433.
 *
 * The whole path is wired here rather than mocked, because the bug lived in the seam between the
 * pieces and every piece was right on its own.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class ReuseDetectionWithHashedTokensTest extends ORMTestCase
{
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
     * The reproduction from the issue: spend RT1, replay it, and the chain RT2 belongs to has to go
     * with it. With the bug this left RT2 usable and the replay looked like an ordinary bad token.
     */
    public function test_replaying_a_spent_token_revokes_the_chain_even_though_storage_is_hashed(): void
    {
        $manager = $this->manager();
        $registry = $this->registry();

        $this->store($manager, 'RT1', 'the-chain');
        $this->store($manager, 'RT2', 'the-chain');
        $this->store($manager, 'RT-elsewhere', 'another-chain');

        // The spend, as the success listener does it: loaded by what the client sent, recorded with
        // that same value, then deleted
        $spent = $manager->get('RT1');
        $this->assertNotNull($spent);
        $this->assertSame('sha256$'.hash('sha256', 'RT1'), $spent->getRefreshToken(), 'The model carries the stored hash, which is the whole difficulty');

        $registry->remember('RT1', $spent);
        $manager->delete($spent);

        $this->entityManager->clear();

        // The replay: the authenticator finds no row and asks the detector about the raw token
        $this->assertNull($manager->get('RT1'));

        $dispatched = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            'gesdinet.refresh_token_reuse_detected',
            static function (RefreshTokenReuseDetectedEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        new RefreshTokenReuseDetector($registry, $manager, $dispatcher)
            ->unknownTokenPresented('RT1', Request::create('/api/token/refresh', 'POST'));

        $this->entityManager->clear();

        $this->assertNull($manager->get('RT2'), 'The chain the replayed token belonged to has to be revoked');
        $this->assertNotNull($manager->get('RT-elsewhere'), 'Another session must be untouched');

        $this->assertInstanceOf(RefreshTokenReuseDetectedEvent::class, $dispatched);
        $this->assertSame('the-chain', $dispatched->getSpentToken()->family);
        $this->assertSame(1, $dispatched->getRevokedTokens(), 'RT1 was already deleted, so RT2 is what is left to revoke');
    }

    /**
     * The ordinary answer, and the one that must not change: a token nobody ever held is wrong, not
     * replayed, and revokes nothing.
     */
    public function test_a_token_that_was_never_spent_revokes_nothing_under_hashing(): void
    {
        $manager = $this->manager();

        $this->store($manager, 'RT1', 'the-chain');

        new RefreshTokenReuseDetector($this->registry(), $manager, new EventDispatcher())
            ->unknownTokenPresented('a-token-nobody-was-given', Request::create('/api/token/refresh', 'POST'));

        $this->entityManager->clear();

        $this->assertNotNull($manager->get('RT1'));
    }

    /**
     * The same sequence without hashing, so the test above is known to be about the seam and not
     * about reuse detection being broken everywhere.
     */
    public function test_the_same_sequence_works_without_hashing(): void
    {
        $manager = new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);
        $registry = $this->registry();

        $this->store($manager, 'RT1', 'the-chain');
        $this->store($manager, 'RT2', 'the-chain');

        $spent = $manager->get('RT1');
        $this->assertNotNull($spent);
        $this->assertSame('RT1', $spent->getRefreshToken(), 'Without hashing the model carries the token itself');

        $registry->remember('RT1', $spent);
        $manager->delete($spent);

        new RefreshTokenReuseDetector($registry, $manager, new EventDispatcher())
            ->unknownTokenPresented('RT1', Request::create('/api/token/refresh', 'POST'));

        $this->entityManager->clear();

        $this->assertNull($manager->get('RT2'));
    }

    private function store(RefreshTokenManagerInterface $manager, string $token, string $family): void
    {
        $refreshToken = RefreshToken::createForUserWithTtl($token, UserCreator::create('someone'), 600);
        $refreshToken->setFamily($family);

        $manager->save($refreshToken);
    }

    private function manager(): RefreshTokenManagerInterface
    {
        return new HashedRefreshTokenManager(
            new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE)
        );
    }

    private function registry(): SpentRefreshTokenRegistryInterface
    {
        return new CacheSpentRefreshTokenRegistry($this->pool, 3600);
    }
}
