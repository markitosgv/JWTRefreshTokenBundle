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
 * Every hop from 1.5 to 3.0, in order.
 *
 * For going the whole way at once. The rules are ordered and none of them undoes another, so the
 * result is the same as running the four sets one after the next — but you lose the chance to run
 * your tests between versions, which is the main reason to do it one hop at a time.
 *
 * Use this when the application is small enough that the whole jump is one piece of work. Import the
 * individual sets when it is not.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(__DIR__.'/gesdinet-jwt-refresh-token-20.php');
    $rectorConfig->import(__DIR__.'/gesdinet-jwt-refresh-token-21.php');
    $rectorConfig->import(__DIR__.'/gesdinet-jwt-refresh-token-22.php');
    $rectorConfig->import(__DIR__.'/gesdinet-jwt-refresh-token-30.php');
};
