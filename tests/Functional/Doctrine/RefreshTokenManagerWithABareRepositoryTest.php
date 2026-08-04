<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Doctrine;

use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
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

    private function manager(): RefreshTokenManager
    {
        $bare = new class($this->entityManager->getRepository(RefreshToken::class)) implements RefreshTokenRepositoryInterface {
            public function __construct(private readonly object $inner)
            {
            }

            public function findInvalid(?\DateTimeInterface $datetime = null): iterable
            {
                return $this->inner->findInvalid($datetime);
            }

            public function findInvalidBatch(?\DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
            {
                return $this->inner->findInvalidBatch($datetime, $batchSize, $offset);
            }

            public function find($id): ?RefreshTokenInterface
            {
                return $this->inner->find($id);
            }

            public function findAll(): array
            {
                return $this->inner->findAll();
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
            {
                return $this->inner->findBy($criteria, $orderBy, $limit, $offset);
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?RefreshTokenInterface
            {
                return $this->inner->findOneBy($criteria, $orderBy);
            }

            public function getClassName(): string
            {
                return RefreshToken::class;
            }
        };

        // The manager takes its repository from the object manager, so that is where the bare one
        // goes rather than being forced onto a readonly property
        $objectManager = new class($this->entityManager, $bare) implements ObjectManager {
            public function __construct(
                private readonly ObjectManager $inner,
                private readonly RefreshTokenRepositoryInterface $repository,
            ) {
            }

            public function getRepository($className): RefreshTokenRepositoryInterface
            {
                return $this->repository;
            }

            public function __call(string $name, array $arguments): mixed
            {
                return $this->inner->{$name}(...$arguments);
            }

            public function find(string $className, $id): ?object
            {
                return $this->inner->find($className, $id);
            }

            public function persist(object $object): void
            {
                $this->inner->persist($object);
            }

            public function remove(object $object): void
            {
                $this->inner->remove($object);
            }

            public function clear(): void
            {
                $this->inner->clear();
            }

            public function detach(object $object): void
            {
                $this->inner->detach($object);
            }

            public function refresh(object $object): void
            {
                $this->inner->refresh($object);
            }

            public function flush(): void
            {
                $this->inner->flush();
            }

            public function getClassMetadata(string $className): ClassMetadata
            {
                return $this->inner->getClassMetadata($className);
            }

            public function getMetadataFactory(): ClassMetadataFactory
            {
                return $this->inner->getMetadataFactory();
            }

            public function initializeObject(object $obj): void
            {
                $this->inner->initializeObject($obj);
            }

            public function isUninitializedObject(mixed $value): bool
            {
                return $this->inner->isUninitializedObject($value);
            }

            public function contains(object $object): bool
            {
                return $this->inner->contains($object);
            }
        };

        return new RefreshTokenManager($objectManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);
    }
}
