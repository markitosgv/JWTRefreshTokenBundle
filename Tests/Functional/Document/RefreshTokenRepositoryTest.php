<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Document;

use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ODMTestCase;

/**
 * @requires extension mongodb
 */
final class RefreshTokenRepositoryTest extends ODMTestCase
{
    /**
     * @var RefreshTokenGenerator
     */
    private $generator;

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
            new RefreshTokenManager($this->documentManager, RefreshToken::class)
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

        $time = new \DateTime();
        $time->modify('+1200 seconds');

        $this->assertCount(5, $repo->findInvalid($time));
    }
}
