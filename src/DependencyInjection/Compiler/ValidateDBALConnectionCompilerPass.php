<?php

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
 * @internal
 */
final class ValidateDBALConnectionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('gesdinet_jwt_refresh_token.dbal.connection')) {
            return;
        }

        $connectionServiceId = $container->getParameter('gesdinet_jwt_refresh_token.dbal.connection');

        if (!\is_string($connectionServiceId) || '' === $connectionServiceId) {
            throw new RuntimeException('The "gesdinet_jwt_refresh_token.dbal.connection" parameter is configured but empty. Please configure a valid DBAL connection service ID.');
        }

        if (!$container->has($connectionServiceId)) {
            $availableConnections = array_filter(
                array_keys($container->getDefinitions()),
                static fn (string $serviceId): bool => str_starts_with($serviceId, 'doctrine.dbal.') && str_ends_with($serviceId, '_connection')
            );

            $errorMessage = sprintf(
                'The DBAL connection service "%s" does not exist. '.
                'Please ensure you have:'."\n".
                '  - Installed doctrine/dbal and doctrine/doctrine-bundle'."\n".
                '  - Configured Doctrine DBAL in your config/packages/doctrine.yaml'."\n".
                '  - Used a valid connection name (e.g., "doctrine.dbal.default_connection")',
                $connectionServiceId
            );

            if (!empty($availableConnections)) {
                $errorMessage .= "\n".sprintf(
                    'Available DBAL connections: %s',
                    implode(', ', $availableConnections)
                );
            }

            throw new RuntimeException($errorMessage);
        }
    }
}
