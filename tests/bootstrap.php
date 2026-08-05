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

require_once dirname(__DIR__).'/vendor/autoload.php';

// Defined here rather than in the <php> section of phpunit.xml.dist so that static analysis and any
// tool running the suite without that configuration file sees them too.
if (!defined('JWTREFRESHTOKENBUNDLE_MONGODB_SERVER')) {
    define('JWTREFRESHTOKENBUNDLE_MONGODB_SERVER', 'mongodb://localhost:27017');
}

if (!defined('JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE')) {
    define('JWTREFRESHTOKENBUNDLE_MONGODB_DATABASE', 'jwt_refresh_token_bundle_odm_tests');
}
