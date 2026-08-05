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
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Renaming\Rector\String_\RenameStringRector;

/**
 * 1.5 to 2.0.
 *
 * The hop with the most to rewrite, because 2.0 dropped the Guard-based authentication Symfony had
 * already removed, and renamed every container id onto one prefix.
 *
 * What it cannot do is the part that matters most: 2.0 replaced a controller calling a service with
 * an authenticator on the firewall, and that is configuration and deleted files rather than a
 * transformation. See UPGRADE-RECTOR.md, which walks through it.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        // Both became plain `RefreshToken`; the abstract in between was doing nothing
        'Gesdinet\JWTRefreshTokenBundle\Entity\AbstractRefreshToken' => 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken',
        'Gesdinet\JWTRefreshTokenBundle\Document\AbstractRefreshToken' => 'Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken',
    ]);

    // Deliberately NOT renamed, though it would be easy:
    //
    // - Security\Authenticator\RefreshTokenAuthenticator, the Guard one, to its replacement at
    //   Security\Http\Authenticator. They share a name and nothing else: every method of the Guard
    //   interface is gone. Renaming it would turn "class not found", which sends you to the upgrade
    //   notes, into a scatter of "method does not exist" at the new class, which does not.
    // - Model\RefreshTokenManager to Model\RefreshTokenManagerInterface. The class was removed and
    //   the interface is what to implement, but `extends RefreshTokenManagerInterface` is not code,
    //   and a type-hint rewritten this way would hide that there is nothing behind it any more.
    // - Service\RefreshToken and Security\Provider\RefreshTokenProvider, which have no replacement
    //   at all: the firewall does what they did.

    $rectorConfig->ruleWithConfiguration(RenameStringRector::class, [
        // Container ids, for `getParameter()`, `get()` and any string map of your own. Every one of
        // these moved onto the `gesdinet_jwt_refresh_token` prefix, and the dotted spelling is gone
        'gesdinet.jwtrefreshtoken.refresh_token_manager' => 'gesdinet_jwt_refresh_token.refresh_token_manager',
        'gesdinet.jwtrefreshtoken.refresh_token_generator' => 'gesdinet_jwt_refresh_token.refresh_token_generator',
        'gesdinet.jwtrefreshtoken.refresh_token.class' => 'gesdinet_jwt_refresh_token.refresh_token.class',
        'gesdinet.jwtrefreshtoken.request.extractor.chain' => 'gesdinet_jwt_refresh_token.request.extractor.chain',
        'gesdinet.jwtrefreshtoken.request.extractor.request_body' => 'gesdinet_jwt_refresh_token.request.extractor.request_body',
        'gesdinet.jwtrefreshtoken.request.extractor.request_cookie' => 'gesdinet_jwt_refresh_token.request.extractor.request_cookie',
        'gesdinet.jwtrefreshtoken.request.extractor.request_parameter' => 'gesdinet_jwt_refresh_token.request.extractor.request_parameter',
        'gesdinet.jwtrefreshtoken.security.authentication.success_handler' => 'gesdinet_jwt_refresh_token.security.authentication.success_handler',
        'gesdinet.jwtrefreshtoken.security.authentication.failure_handler' => 'gesdinet_jwt_refresh_token.security.authentication.failure_handler',
        'gesdinet.jwtrefreshtoken.security.refresh_token_authenticator' => 'gesdinet_jwt_refresh_token.security.refresh_token_authenticator',

        // These have no new spelling: the services are gone. They are renamed anyway, onto ids that
        // do not exist, so the failure is "you have no such service" at the line that wants it
        // rather than a service that silently resolves to something else
        'gesdinet.jwtrefreshtoken' => 'gesdinet_jwt_refresh_token.removed_in_2_0.refresh_service',
        'gesdinet.jwtrefreshtoken.authenticator' => 'gesdinet_jwt_refresh_token.removed_in_2_0.guard_authenticator',
        'gesdinet.jwtrefreshtoken.send_token' => 'gesdinet_jwt_refresh_token.removed_in_2_0.send_token',
        'gesdinet.jwtrefreshtoken.user_provider' => 'gesdinet_jwt_refresh_token.removed_in_2_0.user_provider',
        'gesdinet.jwtrefreshtoken.user_checker' => 'gesdinet_jwt_refresh_token.removed_in_2_0.user_checker',
        'gesdinet.jwtrefreshtoken.object_manager' => 'gesdinet_jwt_refresh_token.removed_in_2_0.object_manager',
    ]);
};
