<?php

declare(strict_types=1);

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Checks that Lexik's blocklist is on when `block_previous_jwt` asks to use it.
 *
 * That service only exists when `lexik_jwt_authentication.blocklist_token.enabled` is true. Without
 * this the container fails on a missing service, naming an id from another bundle and leaving it to
 * be worked out.
 *
 * @internal
 */
final class ValidateBlockedTokenManagerCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('gesdinet_jwt_refresh_token.event_listener.block_previous_jwt')) {
            return;
        }

        if ($container->has('lexik_jwt_authentication.blocked_token_manager')) {
            return;
        }

        throw new RuntimeException('The "block_previous_jwt" option needs the blocklist of LexikJWTAuthenticationBundle, which is off. Turn it on with:'."\n\nlexik_jwt_authentication:\n    blocklist_token:\n        enabled: true\n        cache: cache.app\n\n".'Its store is what a blocked JWT is recorded in, so it has to outlive the request. A shared cache pool rather than a per-process one, where more than one process serves your traffic.');
    }
}
