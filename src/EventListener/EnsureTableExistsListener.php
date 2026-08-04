<?php

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures the refresh tokens table exists before processing the first request.
 *
 * Uses ConfigCache to track table creation across all PHP-FPM workers and container rebuilds.
 * Much more efficient than checking table existence on every worker startup.
 */
final class EnsureTableExistsListener implements EventSubscriberInterface
{
    private ?ConfigCache $cache = null;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly TableSchemaManager $schemaManager,
        private readonly bool $autoCreateTable,
        private readonly string $cacheDir,
        private readonly bool $debug = false,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512], // High priority, before most listeners
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->autoCreateTable || !$event->isMainRequest()) {
            return;
        }

        // Lazy initialize ConfigCache
        $this->cache ??= new ConfigCache(
            $this->cacheDir.'/gesdinet_jwt_refresh_token_table.php',
            $this->debug
        );

        // Fast file existence check - shared across all PHP-FPM workers
        // Gracefully handles immutable deploys where cache might not exist
        if ($this->cache->isFresh()) {
            return;
        }

        try {
            $this->schemaManager->createTableIfNotExists();

            // Try to write cache file - improves performance on subsequent requests
            // Fails silently on immutable deploys or read-only filesystems
            try {
                $this->cache->write('<?php // Refresh tokens table created');
            } catch (\RuntimeException|\InvalidArgumentException $cacheException) {
                // Cache write failed - likely read-only filesystem or immutable deploy
                // This is acceptable - we just won't benefit from the cache optimization
            }
        } catch (\Throwable $e) {
            // Table creation failed - could be:
            // - Insufficient permissions
            // - Connection issues
            // - Schema introspection errors
            // Similar to how Symfony Messenger handles transport creation
            // If the table truly doesn't exist, the next request will fail with a clear error
            $this->logger->error(
                'Failed to auto-create refresh tokens table: {error}',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
        }
    }
}
