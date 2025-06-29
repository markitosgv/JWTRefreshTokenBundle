<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use DateTimeInterface;
use Doctrine\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

/**
 * @template T of RefreshTokenInterface
 *
 * @extends ObjectRepository<T>
 */
interface RefreshTokenRepositoryInterface extends ObjectRepository
{
    /**
     * @return iterable<T>
     */
    public function findInvalid(?DateTimeInterface $datetime = null): iterable;
}
