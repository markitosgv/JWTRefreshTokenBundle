<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Reads back every refresh token issued to a user, for showing them the sessions they have open.
 *
 * Kept apart from RefreshTokenManagerInterface so that an existing implementation of it keeps
 * working without this method.
 *
 * @psalm-mutable
 */
interface ListRefreshTokenManagerInterface
{
    /**
     * Returns every refresh token issued to the given user, the one expiring last first.
     *
     * Tokens that have already expired are included, since leaving them out would hide rows that
     * are still in the table. `isValid()` tells them apart, and a screen listing the sessions a
     * user can still refresh with wants only the valid ones.
     *
     * @return list<RefreshTokenInterface>
     *
     * @psalm-impure it queries the storage
     */
    public function findAllForUser(UserInterface $user): array;
}
