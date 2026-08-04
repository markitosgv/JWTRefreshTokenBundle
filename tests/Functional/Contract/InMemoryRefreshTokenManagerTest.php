<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\InMemoryRefreshTokenManager;
use PHPUnit\Framework\TestCase;

/**
 * The contract held against a manager written outside the bundle, storing the tokens in an array
 * rather than through Doctrine.
 *
 * This is what `refresh_token_manager` promises: the interface can be implemented against any
 * storage. A change to it that only the three shipped managers can satisfy fails here.
 */
final class InMemoryRefreshTokenManagerTest extends TestCase
{
    use RefreshTokenManagerContract;

    private InMemoryRefreshTokenManager $manager;

    protected function setUp(): void
    {
        $this->manager = new InMemoryRefreshTokenManager();
    }

    #[\Override]
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return $this->manager;
    }
}
