<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;

/**
 * 2.0 to 2.1.
 *
 * Empty, and honestly so. Nothing was renamed, moved or removed in 2.1: it fixed methods that were
 * returning the wrong thing, and tightened where invalid input is rejected. A rule set cannot help
 * with either, and one that appeared to do something here would be worse than one that does not.
 *
 * It exists so the chain in UPGRADE-RECTOR.md has no gap — importing all four in order is the
 * documented path, and a missing file in the middle reads as an oversight.
 *
 * What to check by hand, all of it covered in UPGRADE-2.1.md:
 *
 * - `revokeAllInvalidBatch()` now returns every revoked token rather than an empty array. Code that
 *   treated the empty return as "nothing was revoked" was reading a bug as an answer.
 * - `delete()` returns 0 for a token that was not stored. With the ODM it used to return 1 either
 *   way, so anything branching on it behaved differently there.
 * - A repository implementing `RefreshTokenRepositoryInterface` from scratch has to accept the
 *   `$orderBy` argument on `findOneBy()`. Extending Doctrine's own repository is already fine.
 */
return static function (RectorConfig $rectorConfig): void {
};
