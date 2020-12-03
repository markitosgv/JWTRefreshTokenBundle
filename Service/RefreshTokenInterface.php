<?php

namespace Gesdinet\JWTRefreshTokenBundle\Service;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface as ModelRefreshTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

interface RefreshTokenInterface
{
    /**
     * Refresh token.
     *
     * @return mixed
     */
    public function refresh(Request $request);

    /**
     * Creates a token.
     *
     * @return ModelRefreshTokenInterface The refresh token
     */
    public function create(UserInterface $user);
}
