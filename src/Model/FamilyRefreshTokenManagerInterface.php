<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

/**
 * Works on a whole chain of refreshes at once.
 *
 * Revoking one token ends nothing on its own: with `single_use` the client holds a replacement
 * within a request of being given it, so the token you revoked is usually already gone and the
 * session it belonged to carries on. Revoking the family is what ends the session.
 *
 * Kept apart from RevokeRefreshTokenManagerInterface so that an existing implementation of it keeps
 * working without this method.
 *
 * @psalm-mutable
 */
interface FamilyRefreshTokenManagerInterface
{
    /**
     * Revokes every refresh token belonging to the given chain.
     *
     * @return int the number of revoked refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeFamily(string $family): int;
}
