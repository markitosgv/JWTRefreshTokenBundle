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
 * 2.2 to 3.0.
 *
 * Empty, and this one is worth explaining rather than apologising for. A major release usually
 * renames things, and this one deliberately did not: what it changed is configuration, the database
 * schema, and which classes may be extended. None of the three is a code transformation.
 *
 * - `check_path` became required, `dbal_columns` has to name `id`, and every new capability is an
 *   option. All of that is YAML, which Rector does not read.
 * - Refresh tokens gained `family` and `family_valid` columns, which is a migration.
 * - Nine classes became `final`. A rule cannot un-extend them, and the fatal error names the class
 *   and the line, which is as clear as this gets.
 * - `RefreshEvent` gained a required `$request`. A rule could add an argument, but not the right
 *   one: there is no value to invent, and one invented would compile and then be wrong. The bundle
 *   is what dispatches this event, so almost nothing constructs it. If yours does, the missing
 *   argument is an ArgumentCountError with a file and a line.
 *
 * Rather than a rule set that appears to have handled the upgrade, run the whole suite over the
 * checklist in UPGRADE-RECTOR.md, which is where the work actually is.
 */
return static function (RectorConfig $rectorConfig): void {
};
