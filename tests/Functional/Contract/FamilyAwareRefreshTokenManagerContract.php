<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * That a backend actually persists the family, rather than merely accepting it.
 *
 * The failure worth guarding against is silent: a token class can satisfy
 * FamilyAwareRefreshTokenInterface with a property nothing maps, in which case every write is taken
 * and every read comes back null. Nothing throws, and the features built on families quietly treat
 * every token as the start of its own chain. So each test here reads the token back from storage
 * with the identity map emptied first — an object handed back from memory would pass whether the
 * value ever reached the database or not.
 */
trait FamilyAwareRefreshTokenManagerContract
{
    /**
     * @param positive-int $batchSize
     */
    abstract protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface;

    /**
     * Empties whatever sits between the manager and the storage, so the next read is a real one.
     */
    abstract protected function forgetLoadedObjects(): void;

    public function test_reads_back_the_family_it_stored(): void
    {
        $manager = $this->manager();

        $this->storeTokenInFamily($manager, 'a-token-in-a-family', 'the-family');

        $this->forgetLoadedObjects();

        $stored = $manager->get('a-token-in-a-family');

        $this->assertInstanceOf(FamilyAwareRefreshTokenInterface::class, $stored);
        $this->assertSame('the-family', $stored->getFamily(), 'The family did not survive the round trip, so it is not mapped');
    }

    public function test_a_token_stored_without_a_family_reads_back_without_one(): void
    {
        $manager = $this->manager();

        $class = $manager->getClass();
        $manager->save($class::createForUserWithTtl('a-token-with-no-family', UserCreator::create('someone'), 600));

        $this->forgetLoadedObjects();

        $stored = $manager->get('a-token-with-no-family');

        $this->assertInstanceOf(FamilyAwareRefreshTokenInterface::class, $stored);
        $this->assertNull($stored->getFamily(), 'A token stored before families existed reads back without one');
    }

    /**
     * Tokens of one chain share a value; tokens of different chains do not. Everything built on
     * families is a query on this.
     */
    public function test_keeps_the_families_of_different_tokens_apart(): void
    {
        $manager = $this->manager();

        $this->storeTokenInFamily($manager, 'first-of-one-chain', 'one-chain');
        $this->storeTokenInFamily($manager, 'second-of-one-chain', 'one-chain');
        $this->storeTokenInFamily($manager, 'alone-in-another', 'another-chain');

        $this->forgetLoadedObjects();

        $families = array_map(
            function (string $token): ?string {
                $stored = $this->manager()->get($token);

                \assert($stored instanceof FamilyAwareRefreshTokenInterface);

                return $stored->getFamily();
            },
            ['first-of-one-chain', 'second-of-one-chain', 'alone-in-another']
        );

        $this->assertSame(['one-chain', 'one-chain', 'another-chain'], $families);
    }

    private function storeTokenInFamily(RefreshTokenManagerInterface $manager, string $token, string $family): void
    {
        $class = $manager->getClass();
        $refreshToken = $class::createForUserWithTtl($token, UserCreator::create('someone'), 600);

        \assert($refreshToken instanceof FamilyAwareRefreshTokenInterface);

        $refreshToken->setFamily($family);

        $manager->save($refreshToken);
    }
}
