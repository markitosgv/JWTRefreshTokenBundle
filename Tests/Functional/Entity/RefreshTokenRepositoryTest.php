<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Entity;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;

/**
 * @requires extension pdo_sqlite
 */
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
            new RefreshTokenManager($this->entityManager, RefreshToken::class)
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
}
