<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Document;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ODMTestCase;

#[RequiresPhpExtension('mongodb')]
final class RefreshTokenRepositoryTest extends ODMTestCase
{
    private RefreshTokenGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->documentManager->getMetadataFactory()->getAllMetadata() as $class) {
            if ($class->isMappedSuperclass || $class->isEmbeddedDocument || $class->isQueryResultDocument) {
                continue;
            }

            $this->documentManager->getSchemaManager()->createDocumentCollection($class->name);
        }

        $this->documentManager->getSchemaManager()->ensureIndexes();

        $this->generator = new RefreshTokenGenerator(
            new RefreshTokenManager($this->documentManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE),
        );
    }

    public function test_retrieves_no_tokens_when_all_tokens_are_valid(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, 600);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertCount(0, $repo->findInvalid());
    }

    public function test_retrieves_invalid_tokens_when_they_are_expired(): void
    {
        $ttl = 500;

        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, $ttl);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);

            $ttl -= 300;
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertCount(3, $repo->findInvalid());
    }

    public function test_retrieves_all_tokens_older_than_the_specified_time(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User(sprintf('user-%d@localhost', $i));
            $token = $this->generator->createForUserWithTtl($user, 600);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $time = new DateTime();
        $time->modify('+1200 seconds');

        $this->assertCount(5, $repo->findInvalid($time));
    }

    public function test_deletes_all_tokens_for_user(): void
    {
        $users = [];

        for ($i = 0; $i < 2; ++$i) {
            $users[$i] = new User("user-{$i}@localhost");
            $this->documentManager->persist($users[$i]);
        }

        for ($i = 0; $i < 3; ++$i) {
            $token = $this->generator->createForUserWithTtl($users[$i % 2], 600);

            $this->documentManager->persist($token);
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertSame(2, $repo->deleteByUser($users[0]));
        $this->assertEmpty($repo->findBy(['username' => $users[0]->getUserIdentifier()]));
        $this->assertCount(1, $repo->findBy(['username' => $users[1]->getUserIdentifier()]));
    }

    public function test_deletes_all_expired_tokens(): void
    {
        $ttl = 500;

        for ($i = 1; $i <= 5; ++$i) {
            $user = new User("user-{$i}@localhost");
            $token = $this->generator->createForUserWithTtl($user, $ttl);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);

            $ttl -= 300;
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertSame(3, $repo->deleteAllExpired());
        $this->assertCount(2, $repo->findAll());
    }

    public function test_deletes_no_tokens_when_all_are_valid(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            $user = new User("user-{$i}@localhost");
            $token = $this->generator->createForUserWithTtl($user, 500);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertSame(0, $repo->deleteAllExpired());
        $this->assertCount(2, $repo->findAll());
    }

    public function test_deletes_all_tokens_older_than_specified_time(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            $user = new User("user-{$i}@localhost");
            $token = $this->generator->createForUserWithTtl($user, 500);

            $this->documentManager->persist($user);
            $this->documentManager->persist($token);
        }

        $this->documentManager->flush();

        /** @var RefreshTokenRepository $repo */
        $repo = $this->documentManager->getRepository(RefreshToken::class);

        $this->assertSame(2, $repo->deleteAllExpired(new DateTime('+501 seconds')));
    }
}
