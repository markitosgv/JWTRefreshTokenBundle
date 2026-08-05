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
 * Checks that `object_manager` names a service that exists.
 *
 * The option takes a service id, and the name an entity manager is configured under is not one:
 * `entity_manager_2` is the name, `doctrine.orm.entity_manager_2_entity_manager` is the service.
 * Left to itself, Symfony reports it as being unable to replace an alias with a definition, which
 * says nothing about what to write instead.
 *
 * @internal
 */
final class ValidateObjectManagerCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias('gesdinet_jwt_refresh_token.object_manager')) {
            return;
        }

        $objectManagerServiceId = (string) $container->getAlias('gesdinet_jwt_refresh_token.object_manager');

        if ($container->has($objectManagerServiceId)) {
            return;
        }

        $errorMessage = sprintf(
            'The object manager service "%s" does not exist. The "object_manager" option takes a service id rather than the name an entity manager is configured under: an entity manager named "foo" is the service "doctrine.orm.foo_entity_manager".',
            $objectManagerServiceId
        );

        $available = array_filter(
            array_keys($container->getAliases() + $container->getDefinitions()),
            static fn (string $serviceId): bool => (str_starts_with($serviceId, 'doctrine.orm.') && str_ends_with($serviceId, '_entity_manager'))
                || (str_starts_with($serviceId, 'doctrine_mongodb.odm.') && str_ends_with($serviceId, '_document_manager')),
        );

        if ([] !== $available) {
            sort($available);

            $errorMessage .= "\n".sprintf('Available object managers: %s', implode(', ', $available));
        }

        throw new RuntimeException($errorMessage);
    }
}
