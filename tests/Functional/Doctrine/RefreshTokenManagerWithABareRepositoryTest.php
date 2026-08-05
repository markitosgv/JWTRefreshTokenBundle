<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Doctrine;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as BundleRefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\BareRefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\ObjectManagerWithRepository;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use LogicException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * A repository implementing only `RefreshTokenRepositoryInterface`, which is all the bundle has
 * ever asked of one.
 *
 * The repositories that ship implement `DeleteRefreshTokenRepositoryInterface` as well, so every
 * other test takes the path that uses it. An application that wrote its own before that interface
 * existed takes these paths instead, and they are the ones that would rot unnoticed.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class RefreshTokenManagerWithABareRepositoryTest extends ORMTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    public function test_deletes_a_stored_token_by_reading_it_back_first(): void
    {
        $manager = $this->manager();

        $token = RefreshToken::createForUserWithTtl('a-stored-token', UserCreator::create('someone'), 600);
        $manager->save($token);

        $this->assertSame(1, $manager->delete($token));
        $this->assertNull($manager->get('a-stored-token'));
    }

    public function test_reports_no_row_for_a_token_that_was_never_stored(): void
    {
        $token = RefreshToken::createForUserWithTtl('a-token-never-stored', UserCreator::create('someone'), 600);

        $this->assertSame(0, $this->manager()->delete($token));
    }

    /**
     * Revoking by user is a query the base interface does not offer, so the manager says which
     * interface is missing rather than calling a method that is not there.
     */
    public function test_says_which_interface_revoking_by_user_needs(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DeleteRefreshTokenRepositoryInterface');

        $this->manager()->revokeAllForUser(UserCreator::create('someone'));
    }

    public function test_says_the_same_for_pruning(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DeleteRefreshTokenRepositoryInterface');

        $this->manager()->revokeAllButNewestForUser(UserCreator::create('someone'), 1);
    }

    /**
     * Revoking a chain is a query of its own, so it names its own interface rather than the one the
     * other two revocations need.
     */
    public function test_says_which_interface_revoking_a_chain_needs(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DeleteRefreshTokenFamilyRepositoryInterface');

        $this->manager()->revokeFamily('a-chain');
    }

    private function manager(): RefreshTokenManager
    {
        $repository = $this->entityManager->getRepository(RefreshToken::class);

        \assert($repository instanceof BundleRefreshTokenRepository);

        return new RefreshTokenManager(
            new ObjectManagerWithRepository($this->entityManager, new BareRefreshTokenRepository($repository)),
            RefreshToken::class,
            RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE
        );
    }
}
