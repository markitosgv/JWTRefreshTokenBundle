<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\ObjectRepository;

/**
 * @template T of RefreshTokenInterface
 *
 * @extends ObjectRepository<T>
 */
interface RefreshTokenRepositoryInterface extends ObjectRepository
{
    /**
     * @return T[]
     */
    public function findInvalid(?\DateTimeInterface $datetime = null): array;
}
