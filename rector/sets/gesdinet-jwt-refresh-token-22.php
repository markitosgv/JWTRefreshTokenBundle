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

use Rector\Config\RectorConfig;

/*
 * 2.1 to 2.2.
 *
 * Also empty, for a happier reason: 2.2 only added. Every new capability arrived on an interface of
 * its own — `ListRefreshTokenManagerInterface`, `RevokeRefreshTokenManagerInterface`,
 * `DeleteRefreshTokenRepositoryInterface` — precisely so that an existing implementation could
 * ignore it, and the new options are all off by default.
 *
 * The two arguments `AttachRefreshTokenOnSuccessListener` gained are optional and the service is
 * configured by the bundle, so nothing constructing it through the container is affected.
 *
 * What to check by hand, all of it covered in UPGRADE-2.2.md:
 *
 * - `cookie.same_site` no longer accepts values `Symfony\Component\HttpFoundation\Cookie` does not
 *   document. If yours was one of those, the container now refuses to build.
 * - `gesdinet:jwt:clear` lists the revoked tokens only with `-v`. A script parsing its output reads
 *   a count now.
 * - The refresh cookie expires with the token inside it rather than a `ttl` from the moment it was
 *   set, which matters if you were relying on the old, longer window.
 */
return static function (RectorConfig $rectorConfig): void {
};
