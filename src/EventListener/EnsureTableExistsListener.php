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
use RuntimeException;
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

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly TableSchemaManager $schemaManager,
        private readonly bool $autoCreateTable,
        private readonly string $cacheDir,
        private readonly bool $debug = false,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @psalm-pure
     */
    #[\Override]
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
            } catch (\RuntimeException $cacheException) {
                // Cache write failed - likely read-only filesystem or immutable deploy
                // This is acceptable - we just won't benefit from the cache optimization
            }
        } catch (\Throwable $e) {
            // Reported and rethrown: the causes are configuration problems, such as a connection
            // that cannot alter the schema, and swallowing them turns the first refresh into an
            // unrelated error about a missing table
            $this->logger->error(
                'Failed to create the refresh tokens table: {error}',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            throw new RuntimeException(sprintf('The refresh tokens table could not be created: %s. Create it with a migration and turn "dbal_auto_create_table" off.', $e->getMessage()), 0, $e);
        }
    }
}
