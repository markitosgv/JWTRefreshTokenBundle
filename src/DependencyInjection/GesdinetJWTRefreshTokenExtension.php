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

namespace Gesdinet\JWTRefreshTokenBundle\DependencyInjection;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @internal
 */
final class GesdinetJWTRefreshTokenExtension extends ConfigurableExtension
{
    /**
     * @param array{ttl: int, ttl_update: bool, block_jwts_on_revocation: array{enabled: bool, cache: string, ttl: int, user_claim: string}, rate_limiter: array{enabled: bool, limiter: string, key: string}, reuse_detection: array{enabled: bool, cache: string, ttl: int}, max_session_lifetime: int|null, max_tokens_per_user: int|null, single_use: bool, single_use_ttl_update: bool, token_parameter_name: string, cookie?: array<string, mixed>, return_expiration: bool, return_expiration_parameter_name: string, refresh_token_class: class-string<\Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface>, default_invalid_batch_size: int, refresh_token_manager: string|null, cache_pool: string|null, api_platform: array{enabled: bool}, block_previous_jwt: bool, hash_tokens: array{enabled: bool, accept_stored_in_the_clear: bool}, object_manager: string|null, dbal_connection: string|null, dbal_table_name: string, dbal_auto_create_table: bool, dbal_columns: array<string, array{name: string, type: string}>} $mergedConfig
     */
    #[\Override]
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(ExtractorInterface::class)->addTag('gesdinet_jwt_refresh_token.request_extractor');

        $container->setParameter('gesdinet_jwt_refresh_token.ttl', $mergedConfig['ttl']);
        $container->setParameter('gesdinet_jwt_refresh_token.ttl_update', $mergedConfig['ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.max_tokens_per_user', $mergedConfig['max_tokens_per_user']);
        $container->setParameter('gesdinet_jwt_refresh_token.max_session_lifetime', $mergedConfig['max_session_lifetime']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use', $mergedConfig['single_use']);
        $container->setParameter('gesdinet_jwt_refresh_token.single_use_ttl_update', $mergedConfig['single_use_ttl_update']);
        $container->setParameter('gesdinet_jwt_refresh_token.token_parameter_name', $mergedConfig['token_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.cookie', $mergedConfig['cookie'] ?? []);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration', $mergedConfig['return_expiration']);
        $container->setParameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name', $mergedConfig['return_expiration_parameter_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.refresh_token.class', $mergedConfig['refresh_token_class']);
        $container->setParameter('gesdinet_jwt_refresh_token.default_invalid_batch_size', $mergedConfig['default_invalid_batch_size']);

        // Filled in by the authenticator factory, one entry per firewall that configures it. The
        // bundle is usable without one on any firewall, so it has to exist either way — and the
        // factory may have run first, in which case what it recorded stays
        if (!$container->hasParameter('gesdinet_jwt_refresh_token.firewall_options')) {
            $container->setParameter('gesdinet_jwt_refresh_token.firewall_options', []);
        }

        if ($mergedConfig['block_previous_jwt']) {
            $loader->load('block_previous_jwt.php');
        }

        if ($mergedConfig['hash_tokens']['enabled']) {
            $container->setParameter('gesdinet_jwt_refresh_token.hash_tokens.accept_stored_in_the_clear', $mergedConfig['hash_tokens']['accept_stored_in_the_clear']);

            $loader->load('hashed_manager.php');
        }

        if ($mergedConfig['block_jwts_on_revocation']['enabled']) {
            $container->setParameter('gesdinet_jwt_refresh_token.block_jwts_on_revocation.cache', $mergedConfig['block_jwts_on_revocation']['cache']);
            $container->setParameter('gesdinet_jwt_refresh_token.block_jwts_on_revocation.ttl', $mergedConfig['block_jwts_on_revocation']['ttl']);
            $container->setParameter('gesdinet_jwt_refresh_token.block_jwts_on_revocation.user_claim', $mergedConfig['block_jwts_on_revocation']['user_claim']);

            $loader->load('block_jwts_on_revocation.php');
        }

        if ($mergedConfig['rate_limiter']['enabled']) {
            // @codeCoverageIgnoreStart
            // symfony/rate-limiter is a development dependency of this bundle, so the interface is
            // always there while the tests run and this branch cannot be reached from them. Taking
            // it out of the test environment to exercise one throw would cost the RefreshRateLimiter
            // tests, which are worth more.
            if (!interface_exists(RateLimiterFactoryInterface::class)) {
                throw new RuntimeException('The "rate_limiter" option needs the Symfony RateLimiter component. Try running "composer require symfony/rate-limiter".');
            }
            // @codeCoverageIgnoreEnd

            $container->setParameter('gesdinet_jwt_refresh_token.rate_limiter.limiter', $mergedConfig['rate_limiter']['limiter']);
            $container->setParameter('gesdinet_jwt_refresh_token.rate_limiter.key', $mergedConfig['rate_limiter']['key']);

            $loader->load('rate_limiter.php');
        }

        if ($mergedConfig['reuse_detection']['enabled']) {
            if (!$mergedConfig['single_use']) {
                // Without single_use a token is not spent when it is used, so it is never recorded
                // and nothing is ever recognised. Turning this on would cost a cache round trip per
                // failed refresh and detect nothing, while reading in configuration as protection
                throw new InvalidConfigurationException('The "reuse_detection" option needs "single_use" to be true. Without it a refresh token is not replaced when it is used, so there is no spent token for a reuse to be recognised against.');
            }

            $container->setParameter('gesdinet_jwt_refresh_token.reuse_detection.cache', $mergedConfig['reuse_detection']['cache']);
            $container->setParameter('gesdinet_jwt_refresh_token.reuse_detection.ttl', $mergedConfig['reuse_detection']['ttl']);

            $loader->load('reuse_detection.php');
        }

        if ($mergedConfig['api_platform']['enabled']) {
            // @codeCoverageIgnoreStart
            // api-platform/openapi is a development dependency of this bundle, so the interface is
            // always there while the tests run and this branch cannot be reached from them. Taking
            // it out of the test environment to exercise one throw would cost the OpenApiFactory
            // tests, which are worth more.
            if (!interface_exists(OpenApiFactoryInterface::class)) {
                throw new RuntimeException('API Platform cannot be detected. Try running "composer require api-platform/core".');
            }
            // @codeCoverageIgnoreEnd

            // The paths come from the firewall, so the parameter has to exist even when the
            // authenticator is not configured on one
            if (!$container->hasParameter('gesdinet_jwt_refresh_token.check_paths')) {
                $container->setParameter('gesdinet_jwt_refresh_token.check_paths', []);
            }

            $loader->load('api_platform.php');
        }

        if (null !== $mergedConfig['refresh_token_manager']) {
            // Nothing Doctrine is loaded, so the bundle runs on storage it knows nothing about
            $container->setAlias('gesdinet_jwt_refresh_token.refresh_token_manager', $mergedConfig['refresh_token_manager'])
                ->setPublic(true);
        } elseif (null !== $mergedConfig['cache_pool']) {
            $container->setParameter('gesdinet_jwt_refresh_token.cache_pool', $mergedConfig['cache_pool']);

            $loader->load('cache_services.php');
        } elseif (null !== $mergedConfig['dbal_connection']) {
            $this->configureDBALManager($container, $mergedConfig);
            $loader->load('dbal_services.php');
        } else {
            $this->configureObjectManager($container, $mergedConfig);
            $loader->load('om_services.php');
        }
    }

    /**
     * @param array{dbal_connection: string, dbal_table_name: string, dbal_auto_create_table: bool, dbal_columns: array<string, array{name: string, type: string}>} $config
     */
    private function configureDBALManager(ContainerBuilder $container, array $config): void
    {
        $container->setAlias('gesdinet_jwt_refresh_token.dbal.connection', $config['dbal_connection']);

        $container->setParameter('gesdinet_jwt_refresh_token.dbal.connection', $config['dbal_connection']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.table_name', $config['dbal_table_name']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.auto_create_table', $config['dbal_auto_create_table']);
        $container->setParameter('gesdinet_jwt_refresh_token.dbal.columns', $config['dbal_columns']);
    }

    /**
     * @param array{object_manager: string|null} $mergedConfig
     */
    private function configureObjectManager(ContainerBuilder $container, array $mergedConfig): void
    {
        if (null !== $mergedConfig['object_manager']) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', $mergedConfig['object_manager']);
            // @codeCoverageIgnoreStart
            // willBeAvailable() asks whether a package is a runtime dependency of something
            // installed, and names doctrine-bundle and mongodb-odm-bundle as the packages that
            // would make it so. Neither is a dependency here — the tests build entity and document
            // managers directly — so both of these are false whatever is installed. Adding those
            // bundles to require-dev would pull a Symfony application's worth of packages to reach
            // two setAlias() calls.
        } elseif (ContainerBuilder::willBeAvailable('doctrine/orm', EntityManager::class, ['doctrine/doctrine-bundle'])) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine.orm.entity_manager');
        } elseif (ContainerBuilder::willBeAvailable('doctrine/mongodb-odm', DocumentManager::class, ['doctrine/mongodb-odm-bundle'])) {
            $container->setAlias('gesdinet_jwt_refresh_token.object_manager', 'doctrine_mongodb.odm.document_manager');
            // @codeCoverageIgnoreEnd
        } else {
            throw new RuntimeException('The "object_manager" node must be configured when neither "doctrine/orm" or "doctrine/mongodb-odm" are installed.');
        }
    }
}
