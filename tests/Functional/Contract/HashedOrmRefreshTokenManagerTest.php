<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\HashedRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * The shared contracts are deliberately not applied here. They assert that a token read back is the
 * one that was stored, and once the hash is what is stored that stops being true by design: the
 * model carries the stored value, which is the whole point. What is checked instead is that the
 * token never reaches the table, that it is still found by what the client was given, and that
 * everything the manager underneath offers still arrives.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class HashedOrmRefreshTokenManagerTest extends ORMTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    public function test_stores_the_hash_rather_than_the_token(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token-of-my-own', UserCreator::create('someone'), 600);
        $manager->save($token);

        $stored = $this->entityManager->getConnection()->fetchOne('SELECT refresh_token FROM RefreshToken');

        $this->assertSame('sha256$'.hash('sha256', 'a-token-of-my-own'), $stored);
        $this->assertStringNotContainsString('a-token-of-my-own', $stored);
    }

    /**
     * What the model carries after being stored is what storage holds, so the value the client is
     * given has to be read before saving. Leaving the token on the model and putting it back after
     * the write is what the recipe going around does, and it leaves the entity dirty: the next
     * flush writes the token back in the clear.
     */
    public function test_the_token_is_not_left_on_the_model_to_be_written_back(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-token-of-my-own', UserCreator::create('someone'), 600);
        $manager->save($token);

        $this->entityManager->flush();

        $this->assertSame(
            'sha256$'.hash('sha256', 'a-token-of-my-own'),
            $this->entityManager->getConnection()->fetchOne('SELECT refresh_token FROM RefreshToken')
        );
    }

    public function test_finds_a_token_by_what_the_client_was_given(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-token-of-my-own', UserCreator::create('someone'), 600));

        $this->assertNotNull($manager->get('a-token-of-my-own'));
        $this->assertNull($manager->get('a-token-nobody-was-given'));
    }

    public function test_does_not_hash_a_token_read_back_and_saved_again(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-token-of-my-own', UserCreator::create('someone'), 600));

        $read = $manager->get('a-token-of-my-own');

        $this->assertNotNull($read);

        $manager->save($read);

        $this->assertNotNull($manager->get('a-token-of-my-own'), 'Saving it again should not hash the hash');
    }

    /**
     * Turning this on cannot sign everybody out, so a token stored before it is taken as it is and
     * rewritten hashed the first time it is used.
     */
    public function test_takes_a_token_stored_before_hashing_was_turned_on(): void
    {
        $this->storeInTheClear('a-token-from-before');

        $manager = $this->manager();

        $this->assertNotNull($manager->get('a-token-from-before'));

        $this->assertSame(
            'sha256$'.hash('sha256', 'a-token-from-before'),
            $this->entityManager->getConnection()->fetchOne('SELECT refresh_token FROM RefreshToken'),
            'Using it should rewrite it hashed'
        );
    }

    public function test_refuses_a_token_stored_in_the_clear_once_that_is_turned_off(): void
    {
        $this->storeInTheClear('a-token-from-before');

        $manager = new HashedRefreshTokenManager(
            new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE),
            false
        );

        $this->assertNull($manager->get('a-token-from-before'));
    }

    public function test_everything_the_manager_underneath_offers_still_arrives(): void
    {
        $manager = $this->manager();

        \assert($manager instanceof RevokeRefreshTokenManagerInterface);
        \assert($manager instanceof ListRefreshTokenManagerInterface);

        $manager->save(RefreshToken::createForUserWithTtl('one', UserCreator::create('someone'), 600));
        $manager->save(RefreshToken::createForUserWithTtl('two', UserCreator::create('someone'), 300));
        $manager->save(RefreshToken::createForUserWithTtl('expired', UserCreator::create('someone'), -600));

        $this->assertCount(3, $manager->findAllForUser(UserCreator::create('someone')));
        $this->assertSame(
            'sha256$'.hash('sha256', 'one'),
            $manager->getLastFromUsername('someone')?->getRefreshToken(),
            'The one lasting longest, carrying the stored value'
        );
        $this->assertCount(1, $manager->revokeAllInvalid());
        $this->assertSame(1, $manager->revokeAllButNewestForUser(UserCreator::create('someone'), 1));
        $this->assertSame(1, $manager->revokeAllForUser(UserCreator::create('someone')));
    }

    /**
     * The family is not the token, so it is not hashed on the way through. Hashing what the chain is
     * keyed by would leave the tokens of one chain unable to find each other.
     */
    public function test_revokes_a_chain_through_the_manager_underneath(): void
    {
        $manager = $this->manager();

        \assert($manager instanceof FamilyRefreshTokenManagerInterface);

        foreach (['one', 'two'] as $value) {
            $token = RefreshToken::createForUserWithTtl($value, UserCreator::create('someone'), 600);
            $token->setFamily('the-chain');
            $manager->save($token);
        }

        $this->assertSame(2, $manager->revokeFamily('the-chain'));
        $this->assertNull($manager->get('one'));
    }

    public function test_says_so_when_the_manager_underneath_cannot_revoke_a_chain(): void
    {
        $bare = new HashedRefreshTokenManager($this->createStub(RefreshTokenManagerInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement');

        $bare->revokeFamily('a-chain');
    }

    public function test_deletes_the_token_it_was_given(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('a-token-of-my-own', UserCreator::create('someone'), 600));

        $stored = $manager->get('a-token-of-my-own');

        $this->assertNotNull($stored);
        $this->assertSame(1, $manager->delete($stored));
        $this->assertNull($manager->get('a-token-of-my-own'));
    }

    public function test_passes_the_rest_of_the_interface_through(): void
    {
        $manager = $this->manager();

        $manager->save(RefreshToken::createForUserWithTtl('expired', UserCreator::create('someone'), -600));

        $this->assertSame(RefreshToken::class, $manager->getClass());
        $this->assertCount(1, $manager->revokeAllInvalidBatch(null, 10));
    }

    /**
     * Hashing wraps whichever manager is underneath, and one implementing only the base interface
     * cannot be made to revoke by user. Saying which interface is missing beats calling a method
     * that is not there.
     */
    public function test_says_so_when_the_manager_underneath_cannot_revoke_by_user(): void
    {
        $bare = new HashedRefreshTokenManager($this->createStub(RefreshTokenManagerInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement');

        $bare->revokeAllForUser(UserCreator::create('someone'));
    }

    private function storeInTheClear(string $token): void
    {
        $unhashed = new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        $unhashed->save(RefreshToken::createForUserWithTtl($token, UserCreator::create('someone'), 600));

        $this->entityManager->clear();
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new HashedRefreshTokenManager(
            new RefreshTokenManager($this->entityManager, RefreshToken::class, $batchSize)
        );
    }
}
