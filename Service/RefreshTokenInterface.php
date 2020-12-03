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
     * @param Request $request
     *
     * @return mixed
     */
    public function refresh(Request $request);

    /**
     * Creates a token.
     *
     * @param UserInterface $user
     *
     * @return ModelRefreshTokenInterface The refresh token
     */
    public function create(UserInterface $user);
}
