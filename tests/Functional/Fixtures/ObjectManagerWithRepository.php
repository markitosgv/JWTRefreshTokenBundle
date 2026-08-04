<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

/**
 * An object manager that hands out a repository of the test's choosing.
 *
 * The manager takes its repository from the object manager rather than being given one, so this is
 * where a repository under test goes.
 */
final readonly class ObjectManagerWithRepository implements ObjectManager
{
    /**
     * @param ObjectRepository<object> $repository
     */
    public function __construct(
        private ObjectManager $inner,
        private ObjectRepository $repository,
    ) {
    }

    /**
     * @return ObjectRepository<object>
     */
    public function getRepository(string $className): ObjectRepository
    {
        return $this->repository;
    }

    public function find(string $className, mixed $id): ?object
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

    /**
     * @return ClassMetadata<object>
     */
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
}
