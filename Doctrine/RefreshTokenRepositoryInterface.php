<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

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
     * @param \DateTimeInterface|null $datetime
     *
     * @return T[]
     */
    public function findInvalid($datetime = null);
}
