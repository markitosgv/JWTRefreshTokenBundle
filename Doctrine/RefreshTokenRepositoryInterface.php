<?php

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

interface RefreshTokenRepositoryInterface extends ObjectRepository
{
    /**
     * @param null $datetime
     *
     * @return RefreshTokenInterface[]
     */
    public function findInvalid($datetime = null);
}
