<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\DBAL\TableSchemaManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\EnsureTableExistsListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class EnsureTableExistsListenerTest extends TestCase
{
    private Connection $connection;
    private TableSchemaManager $schemaManager;
    private string $cacheDir;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->schemaManager = new TableSchemaManager(
            $this->connection,
            'refresh_tokens',
            []
        );
        $this->logger = new TestLogger();
        $this->cacheDir = sys_get_temp_dir().'/jwt_test_'.uniqid();
        @mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->connection->close();

        // Clean up cache directory
        if (is_dir($this->cacheDir)) {
            $cacheFile = $this->cacheDir.'/gesdinet_jwt_refresh_token_table.php';
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testCreatesTableOnFirstRequest(): void
    {
        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            true,
            $this->cacheDir,
            false,
            $this->logger
        );

        $this->assertFalse($this->schemaManager->tableExists(), 'Table should not exist initially');

        $event = $this->createRequestEvent();
        $listener->onKernelRequest($event);

        $this->assertTrue($this->schemaManager->tableExists(), 'Table should be created');
        $this->assertEmpty($this->logger->logs, 'No errors should be logged on success');
    }

    public function testDoesNotRecreateExistingTable(): void
    {
        // Create table manually first
        $this->schemaManager->createTable();

        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            true,
            $this->cacheDir,
            false,
            $this->logger
        );

        $event = $this->createRequestEvent();
        $listener->onKernelRequest($event);

        $this->assertTrue($this->schemaManager->tableExists());
        $this->assertEmpty($this->logger->logs, 'No errors should be logged when table already exists');
    }

    public function testLogsErrorOnDatabaseFailure(): void
    {
        // Use an invalid table name with special characters that will cause an error
        $badSchemaManager = new TableSchemaManager(
            $this->connection,
            'invalid-table-name-with-special-chars!@#', // Invalid table name
            []
        );

        $listener = new EnsureTableExistsListener(
            $badSchemaManager,
            true,
            $this->cacheDir,
            false,
            $this->logger
        );

        $event = $this->createRequestEvent();
        $listener->onKernelRequest($event);

        // Should have logged an error
        $this->assertCount(1, $this->logger->logs, 'Expected one error to be logged');
        $this->assertSame(LogLevel::ERROR, $this->logger->logs[0]['level']);
        $this->assertStringContainsString('Failed to auto-create refresh tokens table', $this->logger->logs[0]['message']);
        $this->assertArrayHasKey('exception', $this->logger->logs[0]['context']);
        $this->assertArrayHasKey('error', $this->logger->logs[0]['context']);
    }

    public function testWorksWithoutLogger(): void
    {
        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            true,
            $this->cacheDir,
            false,
            null // No logger
        );

        $event = $this->createRequestEvent();

        // Should not throw exception
        $listener->onKernelRequest($event);

        $this->assertTrue($this->schemaManager->tableExists());
    }

    public function testDoesNotCreateTableWhenDisabled(): void
    {
        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            false, // auto_create_table disabled
            $this->cacheDir,
            false,
            $this->logger
        );

        $event = $this->createRequestEvent();
        $listener->onKernelRequest($event);

        $this->assertFalse($this->schemaManager->tableExists(), 'Table should not be created when disabled');
    }

    public function testSkipsTableCreationOnSubRequests(): void
    {
        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            true,
            $this->cacheDir,
            false,
            $this->logger
        );

        $event = $this->createRequestEvent(HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertFalse($this->schemaManager->tableExists(), 'Table should not be created on sub-requests');
    }

    public function testUsesCache(): void
    {
        $listener = new EnsureTableExistsListener(
            $this->schemaManager,
            true,
            $this->cacheDir,
            false,
            $this->logger
        );

        // First request creates table
        $event = $this->createRequestEvent();
        $listener->onKernelRequest($event);

        $this->assertTrue($this->schemaManager->tableExists());

        // Cache file should be created
        $cacheFile = $this->cacheDir.'/gesdinet_jwt_refresh_token_table.php';
        $this->assertFileExists($cacheFile);

        // Drop table to test cache prevents recreation attempt
        $this->schemaManager->dropTable();

        // Second request should use cache and skip table creation
        $event2 = $this->createRequestEvent();
        $listener->onKernelRequest($event2);

        // Table still doesn't exist because cache prevented the check
        $this->assertFalse($this->schemaManager->tableExists());
    }

    private function createRequestEvent(int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');

        return new RequestEvent($kernel, $request, $requestType);
    }
}

/**
 * Simple test logger to capture log messages.
 */
final class TestLogger implements LoggerInterface
{
    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $logs = [];

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
