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

use DateTimeInterface;

/**
 * Clears out expired refresh tokens without reading them first.
 *
 * {@see RefreshTokenManagerInterface::revokeAllInvalid()} and its batched form hydrate every token
 * they delete, because they hand them back and the object manager wants them to remove them. That is
 * what makes them usable from a listener, and what makes them expensive: a backlog of a million dead
 * rows is a million objects through the unit of work. This is one statement sent to the driver, so
 * what it costs does not grow with the size of the backlog.
 *
 * The price is that nothing is hydrated, which means no life-cycle events are raised and the tokens
 * that went cannot be reported, only counted. An application that listens for their removal wants
 * the other method.
 *
 * Kept apart from RefreshTokenManagerInterface so that an existing implementation of it keeps
 * working without this method.
 *
 * @psalm-mutable
 */
interface ExpiredRefreshTokenManagerInterface
{
    /**
     * Deletes every refresh token that had expired by the given moment, now when none is given.
     *
     * Life-cycle events are not raised for the deleted tokens.
     *
     * @return int the number of deleted refresh tokens
     *
     * @psalm-impure it writes to the storage
     */
    public function revokeAllExpired(?DateTimeInterface $datetime = null): int;
}
