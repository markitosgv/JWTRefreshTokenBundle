<?php

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Interface to be implemented by user managers. This adds an additional level
 * of abstraction between your application, and the actual repository.
 *
 * All changes to RefreshTokenInterface objects should happen through this interface.
 */
interface RevokeRefreshTokenManagerInterface
{
    public function revokeAllForUser(UserInterface $user): void;
}
