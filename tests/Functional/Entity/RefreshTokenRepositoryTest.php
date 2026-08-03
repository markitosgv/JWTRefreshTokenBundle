<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Entity;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;

#[RequiresPhpExtension('pdo_sqlite')]
final class RefreshTokenRepositoryTest extends ORMTestCase
{
    private RefreshTokenGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);

        $this->generator = new RefreshTokenGenerator(
            new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE),
        );
    }

    public function test_retrieves_no_tokens_when_all_tokens_are_valid(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, 600);

            $this->entityManager->persist($user);
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $this->assertCount(0, $repo->findInvalid());
    }

    public function test_retrieves_invalid_tokens_when_they_are_expired(): void
    {
        $ttl = 500;

        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, $ttl);

            $this->entityManager->persist($user);
            $this->entityManager->persist($token);

            $ttl -= 300;
        }

        $this->entityManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $this->assertCount(3, $repo->findInvalid());
    }

    public function test_retrieves_only_the_requested_batch_of_invalid_tokens(): void
    {
        $this->persistExpiredTokens(5);

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $this->assertCount(2, $repo->findInvalidBatch(null, 2));
    }

    public function test_retrieves_the_invalid_tokens_left_after_the_offset(): void
    {
        $this->persistExpiredTokens(5);

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $this->assertCount(1, $repo->findInvalidBatch(null, 2, 4));
    }

    /**
     * Every batch is deleted before the next one is read, so the remaining tokens shift down. This
     * is what catches an implementation paging the offset forward and skipping half of them.
     */
    public function test_revokes_every_invalid_token_across_batches(): void
    {
        $this->persistExpiredTokens(5);

        $manager = new RefreshTokenManager($this->entityManager, RefreshToken::class, 2);

        $revokedTokens = $manager->revokeAllInvalidBatch(null, 2);

        $this->assertCount(5, $revokedTokens, 'Every expired token should be revoked and returned');

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $this->assertCount(0, $repo->findInvalid(), 'No expired token should be left in storage');
    }

    public function test_retrieves_all_tokens_older_than_the_specified_time(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, 600);

            $this->entityManager->persist($user);
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->entityManager->getRepository(RefreshToken::class);

        $time = new DateTime();
        $time->modify('+1200 seconds');

        $this->assertCount(5, $repo->findInvalid($time));
    }

    private function persistExpiredTokens(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, -600);

            $this->entityManager->persist($user);
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();
    }
}
